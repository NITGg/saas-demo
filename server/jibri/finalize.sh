#!/bin/bash
# Jibri finalize script — uploads the finished MP4 directly to Bunny CDN via TUS.
# Falls back to MinIO if Bunny credentials are unavailable.
# Called by Jibri as: finalize.sh <recording_dir>

RECORDING_DIR="$1"

MINIO_ENDPOINT="${MINIO_ENDPOINT:-http://minio:9000}"
MINIO_ACCESS_KEY="${MINIO_ACCESS_KEY:-minioadmin}"
MINIO_SECRET_KEY="${MINIO_SECRET_KEY:-minioadmin123}"
MINIO_BUCKET="${MINIO_BUCKET:-academy-recordings}"
MOODLE_URL="${MOODLE_URL:-http://academy_app}"
MOODLE_INTERNAL_URL="${MOODLE_INTERNAL_URL:-http://host.docker.internal:8081}"
MOODLE_CRON_KEY="${MOODLE_CRON_KEY:-}"
MOODLE_NOTIFY_KEY="${MOODLE_NOTIFY_KEY:-academy-cron-2024}"
BUNNY_API_URL="${BUNNY_API_URL:-}"
BUNNY_INTERNAL_KEY="${BUNNY_INTERNAL_KEY:-}"

# NIT SaaS academies: recordings from rooms named nit_<slug>_<cmid>_<hash> are POSTed
# to the academy's record_notify.php, which uploads them to Vimeo (local_vimeo). The
# Jibri host needs NO video credentials for the SaaS path — just the notify key +
# domain. (Bunny/MinIO below still serve the legacy single-tenant academy.)
SAAS_DOMAIN="${SAAS_DOMAIN:-academy2026.nitg-eg.com}"

log() { echo "[finalize] $*" >&2; }

if [ -z "$RECORDING_DIR" ] || [ ! -d "$RECORDING_DIR" ]; then
    log "ERROR: recording dir not found: '$RECORDING_DIR'"
    exit 1
fi

MP4_FILE=$(find "$RECORDING_DIR" -maxdepth 2 -name "*.mp4" | head -1)
if [ -z "$MP4_FILE" ]; then
    log "No MP4 found in $RECORDING_DIR"
    exit 0
fi

log "Found recording: $MP4_FILE"

FILENAME=$(basename "$MP4_FILE")
TITLE="${FILENAME%.mp4}"

# Extract the Moodle cmid from the room name (academy_jitsi_{cmid}_{hash}_…)
CMID=$(echo "$TITLE" | sed 's/academy_jitsi_\([0-9]*\)_.*/\1/')
[ "$CMID" = "$TITLE" ] && CMID="" # sed returned unchanged — no match

# ════════════════════════════════════════════════════════════════════════════
#  NIT SaaS branch: rooms named nit_<slug>_<cmid>_<hash> belong to a multi-tenant
#  academy → POST the .mp4 to THAT academy's record_notify.php, which uploads it to
#  Vimeo (via local_vimeo) and stores it. The Jibri host needs NO video credentials
#  — only MOODLE_NOTIFY_KEY + SAAS_DOMAIN. Legacy single-tenant rooms
#  (academy_jitsi_…) fall through to the Bunny/MinIO flow below, unchanged.
# ════════════════════════════════════════════════════════════════════════════
SAAS_ROOM=""
[ -f "$RECORDING_DIR/metadata.json" ] && SAAS_ROOM=$(grep -oE 'nit_[a-z0-9-]+_[0-9]+_[a-f0-9]+' "$RECORDING_DIR/metadata.json" | head -1)
[ -n "$SAAS_ROOM" ] || SAAS_ROOM=$(printf '%s' "$TITLE" | grep -oE 'nit_[a-z0-9-]+_[0-9]+_[a-f0-9]+' | head -1)

if [ -n "$SAAS_ROOM" ]; then
    SAAS_SLUG=$(printf '%s' "$SAAS_ROOM" | sed -E 's/^nit_(.+)_([0-9]+)_[a-f0-9]+$/\1/')
    SAAS_CMID=$(printf '%s' "$SAAS_ROOM" | sed -E 's/^nit_(.+)_([0-9]+)_[a-f0-9]+$/\2/')
    REC_TITLE="Recording $(date '+%Y-%m-%d %H:%M')"
    SAAS_BASE=$(grep -m1 "^${SAAS_SLUG} " /config/saas-urls.conf 2>/dev/null | cut -d" " -f2)
    SAAS_BASE="${SAAS_BASE:-https://${SAAS_SLUG}.${SAAS_DOMAIN}}"
    NOTIFY_URL="${SAAS_BASE%/}/mod/jitsi/record_notify.php"
    log "SaaS recording: academy=$SAAS_SLUG cmid=$SAAS_CMID → $NOTIFY_URL (Vimeo via record_notify, $(du -h "$MP4_FILE" | cut -f1))"

    # Server-to-server multipart upload of the recording. --max-time high for big files.
    NOTIFY_CODE=$(curl -s -o /tmp/rec_notify.out -w "%{http_code}" --max-time 3600 \
        -X POST "$NOTIFY_URL" \
        -H "X-Notify-Key: ${MOODLE_NOTIFY_KEY}" \
        -F "cmid=${SAAS_CMID}" \
        -F "title=${REC_TITLE}" \
        -F "file=@${MP4_FILE};type=video/mp4")
    if [ "$NOTIFY_CODE" -ge 200 ] 2>/dev/null && [ "$NOTIFY_CODE" -lt 300 ] 2>/dev/null; then
        log "record_notify OK (HTTP $NOTIFY_CODE): $(cat /tmp/rec_notify.out 2>/dev/null). Removing local recording."
        rm -rf "$RECORDING_DIR"
    else
        log "ERROR: record_notify HTTP ${NOTIFY_CODE} ($(cat /tmp/rec_notify.out 2>/dev/null)) — files kept for retry"
    fi
    exit 0
fi

# ── Upload directly to Bunny via TUS ─────────────────────────────────────────
if [ -n "$BUNNY_API_URL" ] && [ -n "$BUNNY_INTERNAL_KEY" ]; then
    log "Getting Bunny TUS upload credentials..."

    INTENT=$(curl -sf -X POST "${BUNNY_API_URL}/api/internal/upload-intent" \
        -H "Content-Type: application/json" \
        -H "X-Internal-Key: ${BUNNY_INTERNAL_KEY}" \
        -d "{\"title\": \"${TITLE}\"}")

    if [ $? -ne 0 ] || [ -z "$INTENT" ]; then
        log "Failed to get upload intent — falling back to MinIO"
    else
        BUNNY_VIDEO_ID=$(echo "$INTENT" | grep -o '"bunnyVideoId":"[^"]*"' | cut -d'"' -f4)
        AUTH_SIG=$(echo "$INTENT"       | grep -o '"authSignature":"[^"]*"' | cut -d'"' -f4)
        AUTH_EXPIRY=$(echo "$INTENT"    | grep -o '"authExpiry":[0-9]*'     | cut -d':' -f2)
        LIBRARY_ID=$(echo "$INTENT"     | grep -o '"libraryId":"*[0-9]*"*'   | grep -o '[0-9]*')

        FILE_SIZE=$(stat -c%s "$MP4_FILE")

        log "Uploading directly to Bunny TUS (videoId=$BUNNY_VIDEO_ID, size=${FILE_SIZE})..."

        # Step 1 — initiate TUS upload, capture Location header.
        # Use -D to dump headers to stdout and -o /dev/null for body;
        # avoid -I which sends HEAD regardless of -X POST.
        TUS_INIT_HEADERS=$(curl -s -D - -o /dev/null \
            -X POST "https://video.bunnycdn.com/tusupload" \
            -H "AuthorizationSignature: ${AUTH_SIG}" \
            -H "AuthorizationExpire: ${AUTH_EXPIRY}" \
            -H "VideoId: ${BUNNY_VIDEO_ID}" \
            -H "LibraryId: ${LIBRARY_ID}" \
            -H "Tus-Resumable: 1.0.0" \
            -H "Upload-Length: ${FILE_SIZE}" \
            -H "Content-Length: 0")
        TUS_INIT_STATUS=$(echo "$TUS_INIT_HEADERS" | head -1 | tr -d '\r')
        TUS_LOCATION=$(echo "$TUS_INIT_HEADERS" | grep -i "^location:" | tr -d '\r' | awk '{print $2}')
        # Location may be a relative path — make it absolute
        case "$TUS_LOCATION" in
            /*)  TUS_LOCATION="https://video.bunnycdn.com${TUS_LOCATION}" ;;
        esac
        log "TUS initiation response: ${TUS_INIT_STATUS}"

        if [ -z "$TUS_LOCATION" ]; then
            log "TUS initiation failed (no Location header) — falling back to MinIO"
        else
            log "TUS location: $TUS_LOCATION"

            # Step 2 — upload in 50 MB chunks (matches tus-js-client web app behaviour)
            CHUNK_SIZE=$((50 * 1024 * 1024))
            CHUNK_TEMP="/tmp/tus_chunk_${BUNNY_VIDEO_ID}.bin"
            OFFSET=0
            CHUNK_NUM=0
            TUS_OK=true

            while [ $OFFSET -lt $FILE_SIZE ]; do
                dd if="$MP4_FILE" bs=$CHUNK_SIZE skip=$CHUNK_NUM count=1 \
                   of="$CHUNK_TEMP" 2>/dev/null
                ACTUAL_CHUNK=$(stat -c%s "$CHUNK_TEMP")

                log "  Chunk $CHUNK_NUM: offset=${OFFSET} size=${ACTUAL_CHUNK}"

                CHUNK_CODE=$(curl -s -o /tmp/tus_response.txt -w "%{http_code}" \
                    -X PATCH "$TUS_LOCATION" \
                    -H "Tus-Resumable: 1.0.0" \
                    -H "Content-Type: application/offset+octet-stream" \
                    -H "Upload-Offset: ${OFFSET}" \
                    -H "Content-Length: ${ACTUAL_CHUNK}" \
                    -H "AuthorizationSignature: ${AUTH_SIG}" \
                    -H "AuthorizationExpire: ${AUTH_EXPIRY}" \
                    -H "VideoId: ${BUNNY_VIDEO_ID}" \
                    -H "LibraryId: ${LIBRARY_ID}" \
                    --data-binary @"$CHUNK_TEMP")

                if [ "$CHUNK_CODE" != "204" ]; then
                    CHUNK_BODY=$(cat /tmp/tus_response.txt 2>/dev/null)
                    log "TUS chunk $CHUNK_NUM failed (HTTP ${CHUNK_CODE}): ${CHUNK_BODY}"
                    TUS_OK=false
                    break
                fi

                OFFSET=$((OFFSET + ACTUAL_CHUNK))
                CHUNK_NUM=$((CHUNK_NUM + 1))
                log "  Progress: ${OFFSET}/${FILE_SIZE} bytes"
            done

            rm -f "$CHUNK_TEMP"

            if [ "$TUS_OK" = "true" ]; then
                log "Bunny TUS upload complete. Removing local recording."
                rm -rf "$RECORDING_DIR"

                # Notify Moodle so the recording card appears in view.php immediately.
                NOTIFY_URL="${MOODLE_INTERNAL_URL}/mod/jitsi/record_notify.php"
                NOTIFY_CODE=$(curl -s -o /dev/null -w "%{http_code}" \
                    -X POST "$NOTIFY_URL" \
                    -H "X-Notify-Key: ${MOODLE_NOTIFY_KEY}" \
                    --data-urlencode "title=${TITLE}" \
                    --data-urlencode "bunny_video_id=${BUNNY_VIDEO_ID}" \
                    --data-urlencode "cmid=${CMID:-0}")
                if [ "$NOTIFY_CODE" -ge 200 ] && [ "$NOTIFY_CODE" -lt 300 ] 2>/dev/null; then
                    log "Moodle record_notify OK (HTTP ${NOTIFY_CODE})"
                else
                    log "Moodle record_notify failed (HTTP ${NOTIFY_CODE}) — non-fatal"
                fi
                exit 0
            else
                log "TUS chunked upload failed — falling back to MinIO"
            fi
        fi
    fi
fi

# ── Fallback: upload to MinIO ─────────────────────────────────────────────────
DIRNAME=$(basename "$RECORDING_DIR")
OBJECT_KEY="recordings/${DIRNAME}/${FILENAME}"

DATE=$(date -u "+%a, %d %b %Y %H:%M:%S GMT")
RESOURCE="/${MINIO_BUCKET}/"
SIG_STRING=$(printf "PUT\n\n\n%s\n%s" "$DATE" "$RESOURCE")
SIG=$(printf '%s' "$SIG_STRING" | openssl dgst -sha1 -hmac "$MINIO_SECRET_KEY" -binary | base64)
curl -sf -X PUT \
    -H "Date: ${DATE}" \
    -H "Authorization: AWS ${MINIO_ACCESS_KEY}:${SIG}" \
    "${MINIO_ENDPOINT}/${MINIO_BUCKET}/" >/dev/null 2>&1 || true

log "Uploading to MinIO: ${MINIO_ENDPOINT}/${MINIO_BUCKET}/${OBJECT_KEY}"

CONTENT_TYPE="video/mp4"
DATE=$(date -u "+%a, %d %b %Y %H:%M:%S GMT")
RESOURCE="/${MINIO_BUCKET}/${OBJECT_KEY}"
SIG_STRING=$(printf "PUT\n\n%s\n%s\n%s" "$CONTENT_TYPE" "$DATE" "$RESOURCE")
SIG=$(printf '%s' "$SIG_STRING" | openssl dgst -sha1 -hmac "$MINIO_SECRET_KEY" -binary | base64)

HTTP_CODE=$(curl -s -o /tmp/minio_upload_response.txt -w "%{http_code}" -X PUT \
    -H "Date: ${DATE}" \
    -H "Content-Type: ${CONTENT_TYPE}" \
    -H "Authorization: AWS ${MINIO_ACCESS_KEY}:${SIG}" \
    --data-binary @"$MP4_FILE" \
    "${MINIO_ENDPOINT}/${MINIO_BUCKET}/${OBJECT_KEY}")

if [ "$HTTP_CODE" -ge 200 ] && [ "$HTTP_CODE" -lt 300 ]; then
    log "MinIO upload successful (HTTP ${HTTP_CODE}). Removing local recording."
    rm -rf "$RECORDING_DIR"

    # Notify Moodle so the recording card appears in view.php.
    # bunny_video_id may be empty here if TUS intent also failed — that's fine,
    # record_notify will create a placeholder row that polling will later fill in.
    if [ -n "$MOODLE_NOTIFY_KEY" ]; then
        NOTIFY_URL="${MOODLE_INTERNAL_URL}/mod/jitsi/record_notify.php"
        NOTIFY_CODE=$(curl -s -o /dev/null -w "%{http_code}" \
            -X POST "$NOTIFY_URL" \
            -H "X-Notify-Key: ${MOODLE_NOTIFY_KEY}" \
            --data-urlencode "title=${TITLE}" \
            --data-urlencode "bunny_video_id=${BUNNY_VIDEO_ID:-}" \
            --data-urlencode "cmid=${CMID:-0}")
        if [ "$NOTIFY_CODE" -ge 200 ] && [ "$NOTIFY_CODE" -lt 300 ] 2>/dev/null; then
            log "Moodle record_notify OK (HTTP ${NOTIFY_CODE})"
        else
            log "Moodle record_notify failed (HTTP ${NOTIFY_CODE}) — non-fatal"
        fi
    fi

    if [ -n "$MOODLE_CRON_KEY" ]; then
        curl -sf "${MOODLE_INTERNAL_URL}/admin/cron.php?password=${MOODLE_CRON_KEY}" >/dev/null 2>&1 && \
            log "Moodle cron triggered." || log "Moodle cron trigger failed (non-fatal)."
    fi
    exit 0
else
    RESPONSE=$(cat /tmp/minio_upload_response.txt 2>/dev/null)
    log "MinIO upload FAILED (HTTP ${HTTP_CODE}): ${RESPONSE}"
    exit 1
fi
