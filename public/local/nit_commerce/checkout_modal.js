/*
 * NIT shared checkout modal (courses + subscriptions).
 *
 * A page mints its config + strings, then calls NitCheckout.open({...}) from a Buy button. The modal
 * previews the price (auto offer + optional coupon) via /local/nit_commerce/api.php?function=preview_discount
 * and, on Proceed, calls the caller's proceed(couponCode) to start the real Kashier checkout.
 *
 * Usage (from a server page):
 *   window.NIT_CO = { wwwroot: M.cfg.wwwroot, sesskey: M.cfg.sesskey,
 *                     commerce: '/local/nit_commerce/api.php', str: {...} };
 *   NitCheckout.open({ itemType:'course', itemId:10, name:'...', subtitle:'',
 *                      proceed: function(code){ location = checkoutUrl + '&coupon_code=' + code; } });
 *
 * DESIGN: this is the T1 "Order summary" panel from the academy app screens — a LIGHT card
 * (brand accent only). The card / billing form in that mock is Kashier's hosted page, not ours,
 * so we never render or collect card data here; Proceed hands off to Kashier. The price/coupon/offer
 * preview + proceed(coupon) logic is unchanged from the previous version — only the styling moved.
 */
(function (w) {
  'use strict';

  var cfg = null, modal = null, els = {}, current = null;

  function S(k) { return (cfg && cfg.str && cfg.str[k] != null) ? cfg.str[k] : k; }
  function money(n) { return (Math.round(Number(n) * 100) / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  function cur() { return S('co_currency'); }

  function el(tag, style, text) {
    var e = document.createElement(tag);
    if (style) { e.style.cssText = style; }
    if (text != null) { e.textContent = text; }
    return e;
  }

  // Structure follows the ACTIVE TEMPLATE via the --t-* tokens on :root (Phase 2),
  // with T1 literals as fallbacks; only the accent comes from the academy brand.
  var C = {
    bg: 'var(--t-bg, #FFFFFF)',
    surface: 'var(--t-surface, #FAFAF8)',
    ink: 'var(--t-ink, #16191D)',
    muted: 'var(--t-muted, #6E7781)',
    faint: 'var(--t-muted2, #8A8A82)',
    line: 'var(--t-line, #F1F1ED)',
    border: 'var(--t-border, #EDEDE9)',
    field: 'var(--t-field, #FBFBF9)',
    accent: 'var(--nit-brand-primary, #0E7C66)',
    on: 'var(--nit-brand-on-primary, #ffffff)',
    accentsoft: 'color-mix(in srgb, var(--nit-brand-primary, #0E7C66) 8%, var(--t-bg, #ffffff))',
    accentwash: 'color-mix(in srgb, var(--nit-brand-primary, #0E7C66) 30%, var(--t-border, #B9D8D0))',
    danger: '#B4402F'
  };

  function build() {
    if (modal) { return; }
    modal = el('div', 'display:none; position:fixed; inset:0; background:rgba(20,24,28,.42); z-index:99999; align-items:center; justify-content:center; padding:16px; font-family:"Manrope","IBM Plex Sans Arabic",system-ui,sans-serif;');

    var card = el('div', 'width:100%; max-width:420px; max-height:92vh; overflow:auto; background:' + C.bg + '; border:1px solid ' + C.border + '; border-radius:16px; box-shadow:0 24px 60px rgba(20,24,28,.22);');

    // ── Header: order summary title.
    var head = el('div', 'padding:20px 22px; border-bottom:1px solid ' + C.line + '; display:flex; align-items:center; justify-content:space-between; gap:12px;');
    head.appendChild(el('div', 'font-size:15px; font-weight:600; color:' + C.ink + ';', S('co_title')));
    els.headclose = el('button', 'background:transparent; border:0; color:' + C.faint + '; font-size:22px; line-height:1; cursor:pointer; padding:0 2px;', '×');
    els.headclose.type = 'button';
    els.headclose.setAttribute('aria-label', S('co_cancel'));
    head.appendChild(els.headclose);
    card.appendChild(head);

    var body = el('div', 'padding:22px;');

    // ── The item being purchased.
    var item = el('div', 'margin-bottom:6px;');
    els.name = el('div', 'font-size:16px; font-weight:600; letter-spacing:-0.015em; color:' + C.ink + ';', '—');
    item.appendChild(els.name);
    els.subtitle = el('div', 'font-size:12px; color:' + C.faint + '; margin-top:5px; display:none;');
    item.appendChild(els.subtitle);
    body.appendChild(item);

    // ── Promo code row (dashed input + dark Apply, per the mock).
    var cRow = el('div', 'display:flex; gap:8px; margin-top:18px;');
    els.coupon = el('input', 'flex:1; min-width:0; height:44px; border:1px dashed ' + C.accentwash + '; border-radius:9px; background:' + C.accentsoft + '; color:' + C.accent + '; padding:0 13px; font-size:13px; font-weight:600; font-family:inherit;');
    els.coupon.type = 'text'; els.coupon.autocomplete = 'off';
    els.coupon.placeholder = S('co_coupon');
    cRow.appendChild(els.coupon);
    els.apply = el('button', 'flex:0 0 auto; cursor:pointer; font-family:inherit; font-size:13px; font-weight:600; background:' + C.ink + '; border:0; color:#fff; padding:0 18px; border-radius:9px;', S('co_apply'));
    els.apply.type = 'button';
    cRow.appendChild(els.apply);
    body.appendChild(cRow);

    els.couponErr = el('div', 'display:none; color:' + C.danger + '; font-size:12px; margin-top:8px;', ' ');
    body.appendChild(els.couponErr);

    // ── Line items: subtotal, auto-offer, coupon discount.
    var lines = el('div', 'display:flex; flex-direction:column; gap:13px; margin-top:22px; padding-top:20px; border-top:1px solid ' + C.line + ';');
    lines.appendChild(row(S('co_total_sub'), (els.original = el('span', 'color:' + C.ink + ';', '—'))));
    els.offerRow = row(S('co_offer'), (els.offer = el('span', 'color:' + C.accent + ';', '—')));
    els.offerRow.style.display = 'none';
    lines.appendChild(els.offerRow);
    els.discountRow = row(S('co_discount'), (els.discount = el('span', 'color:' + C.accent + ';', '0.00 ' + cur())));
    els.discountRow.style.display = 'none';
    lines.appendChild(els.discountRow);
    body.appendChild(lines);

    // ── Total.
    var totalRow = el('div', 'display:flex; justify-content:space-between; align-items:baseline; margin-top:20px; padding-top:18px; border-top:1px solid ' + C.border + ';');
    totalRow.appendChild(el('span', 'font-size:15px; font-weight:600; color:' + C.ink + ';', S('co_total')));
    els.final = el('span', 'font-size:28px; font-weight:300; letter-spacing:-0.025em; color:' + C.ink + ';', '—');
    totalRow.appendChild(els.final);
    body.appendChild(totalRow);

    // ── Pay & enrol (brand accent), then trust notes.
    els.proceed = el('button', 'cursor:pointer; font-family:inherit; width:100%; margin-top:20px; font-size:15px; font-weight:600; background:' + C.accent + '; border:0; color:' + C.on + '; padding:15px; border-radius:10px; box-shadow:0 12px 26px color-mix(in srgb, var(--nit-brand-primary, #0E7C66) 24%, transparent);', S('co_proceed'));
    els.proceed.type = 'button';
    body.appendChild(els.proceed);

    els.error = el('div', 'display:none; color:' + C.danger + '; font-size:13px; margin-top:12px; text-align:center;', ' ');
    body.appendChild(els.error);

    var secure = el('div', 'font-size:12px; color:' + C.faint + '; text-align:center; margin-top:14px; line-height:1.6;', S('co_secure'));
    body.appendChild(secure);

    var actions = el('div', 'margin-top:14px; text-align:center;');
    els.cancel = el('button', 'background:transparent; border:0; color:' + C.muted + '; font-family:inherit; font-size:13px; font-weight:600; cursor:pointer; padding:4px 8px;', S('co_cancel'));
    els.cancel.type = 'button';
    actions.appendChild(els.cancel);
    body.appendChild(actions);

    card.appendChild(body);
    modal.appendChild(card);
    document.body.appendChild(modal);

    // Wiring.
    els.cancel.addEventListener('click', close);
    els.headclose.addEventListener('click', close);
    modal.addEventListener('click', function (ev) { if (ev.target === modal) { close(); } });
    document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape' && modal.style.display !== 'none') { close(); } });
    els.apply.addEventListener('click', function () { preview(els.coupon.value.trim()); });
    els.coupon.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') { ev.preventDefault(); preview(els.coupon.value.trim()); } });
    els.proceed.addEventListener('click', function () {
      if (!current) { return; }
      els.proceed.disabled = true;
      try { current.proceed(els.coupon.value.trim()); }
      catch (e) { els.proceed.disabled = false; els.error.textContent = String(e && e.message || e); els.error.style.display = ''; }
    });
  }

  function row(label, valueEl) {
    var r = el('div', 'display:flex; justify-content:space-between; font-size:14px; color:' + C.muted + ';');
    r.appendChild(el('span', '', label));
    r.appendChild(valueEl);
    return r;
  }

  function close() { if (modal) { modal.style.display = 'none'; } current = null; }

  // Fetch a fresh price preview (auto offer + optional coupon) and paint the modal.
  function preview(code) {
    if (!current) { return; }
    var url = cfg.wwwroot + cfg.commerce + '?function=preview_discount&item_type=' + encodeURIComponent(current.itemType) +
      '&item_id=' + encodeURIComponent(current.itemId) + '&coupon_code=' + encodeURIComponent(code || '') +
      '&sesskey=' + encodeURIComponent(cfg.sesskey);
    fetch(url, { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || res.status !== 'success') { throw new Error('preview failed'); }
        var d = res.data || {};
        els.original.textContent = money(d.original != null ? d.original : (current.price || 0)) + ' ' + cur();
        els.final.innerHTML = '';
        els.final.appendChild(document.createTextNode(money(d.final != null ? d.final : (current.price || 0)) + ' '));
        els.final.appendChild(el('span', 'font-size:13px; color:' + C.faint + '; font-weight:500;', cur()));
        // Offer line (auto-applied), with its name if present.
        var offerDisc = Number(d.offer_discount || 0);
        if (offerDisc > 0) {
          var oname = (d.offers && d.offers[0] && d.offers[0].name) ? d.offers[0].name : (d.offer_name || '');
          els.offer.textContent = '−' + money(offerDisc) + ' ' + cur() + (oname ? ('  (' + oname + ')') : '');
          els.offerRow.style.display = '';
        } else {
          els.offerRow.style.display = 'none';
        }
        // Coupon discount line (only the coupon portion, if any).
        var couponDisc = Number(d.discount || 0) - offerDisc;
        if (couponDisc > 0.001 && code) {
          els.discount.textContent = '−' + money(couponDisc) + ' ' + cur();
          els.discountRow.style.display = '';
        } else {
          els.discountRow.style.display = 'none';
        }
        if (d.coupon_error) { els.couponErr.textContent = d.coupon_error; els.couponErr.style.display = ''; }
        else { els.couponErr.style.display = 'none'; }
      })
      .catch(function () { els.couponErr.textContent = S('co_coupon_failed'); els.couponErr.style.display = ''; });
  }

  var NitCheckout = {
    init: function (config) { cfg = config; build(); },
    open: function (item) {
      if (!cfg) { return; }
      build();
      current = item;
      els.name.textContent = item.name || '—';
      if (item.subtitle) { els.subtitle.textContent = item.subtitle; els.subtitle.style.display = ''; }
      else { els.subtitle.style.display = 'none'; }
      els.coupon.value = '';
      els.couponErr.style.display = 'none';
      els.error.style.display = 'none';
      els.proceed.disabled = false;
      var base = money(item.price || 0) + ' ' + cur();
      els.original.textContent = base;
      els.final.innerHTML = '';
      els.final.appendChild(document.createTextNode(money(item.price || 0) + ' '));
      els.final.appendChild(el('span', 'font-size:13px; color:' + C.faint + '; font-weight:500;', cur()));
      els.discountRow.style.display = 'none';
      els.offerRow.style.display = 'none';
      modal.style.display = 'flex';
      preview(''); // Auto-apply any offer + fetch the true base.
    },
    close: close
  };

  w.NitCheckout = NitCheckout;
})(window);
