# دليل إشعارات الموبايل الفورية — Firebase Cloud Messaging (FCM)
# Mobile App Push Notifications Integration Guide

> **ملاحظة:** تم استبدال خدمة Airnotifier القديمة بالكامل، وأصبح السيرفر يرسل الإشعارات الفورية مباشرة عبر **Google Firebase Cloud Messaging (HTTP v1)** باستخدام إضافة `message_fcm`. لا يحتاج السيرفر لأي بروكسي خارجي.

---

## 1. الملفات المطلوبة من Firebase وإعداد التطبيق

يجب على مطور التطبيق إضافة ملفات الإعداد الخاصة بفايربيس داخل مشروع التطبيق:

| المنصة | الملف المطلوب | المكان في المشروع |
|---|---|---|
| **Android** | `google-services.json` | داخل مجلد `android/app/` |
| **iOS** | `GoogleService-Info.plist` | داخل مجلد `ios/Runner/` (عبر Xcode) |

### ملاحظة خاصة بنظام iOS (Apple Push Notifications):
لتصل الإشعارات لأجهزة آيفون، يجب على مسؤول حساب **Apple Developer** رفع مفتاح **APNs Authentication Key** (ملف بصيغة `.p8`) داخل لوحة تحكم Firebase:
* ادخل على **Firebase Console** ← **Project settings** ← تبويب **Cloud Messaging**.
* انزل عند **Apple app configuration** وارفع ملف الـ `.p8` مع كتابة الـ `Key ID` والـ `Team ID`.

---

## 2. المكتبات المقترحة (SDKs)

* **Flutter:** مكتبة [`firebase_messaging`](https://pub.dev/packages/firebase_messaging) و [`firebase_core`](https://pub.dev/packages/firebase_core).
* **React Native:** مكتبة [`@react-native-firebase/messaging`](https://rnfirebase.io/messaging/usage).

---

## 3. دورة حياة الـ FCM Token في التطبيق

### أولاً: طلب الصلاحية واستخراج الـ Token
عند تشغيل التطبيق، اطلب إذن الإشعارات من المستخدم، ثم استخرج الـ Token:

```dart
// مثال بلغة Flutter / Dart:
NotificationSettings settings = await FirebaseMessaging.instance.requestPermission(
  alert: true,
  badge: true,
  sound: true,
);

String? fcmToken = await FirebaseMessaging.instance.getToken();
```

---

### ثانياً: تسجيل الجهاز في السيرفر (عند تسجيل الدخول أو تحديث الـ Token)

فور نجاح تسجيل دخول المستخدم في التطبيق، أو عند تغير الـ Token عبر `FirebaseMessaging.instance.onTokenRefresh`، قم بإرسال الـ Token إلى السيرفر عبر الـ Web Service التالي:

* **Endpoint:**
  `POST https://<YOUR_DOMAIN>/webservice/rest/server.php`
* **Query Parameters:**
  * `wstoken`: رمز المصادقة الخاص بالمستخدم المسجل (User Web Service Token).
  * `wsfunction`: `core_user_add_user_device`
  * `moodlewsrestformat`: `json`

* **Request Body (POST Data):**

| Parameter | Type | Required | Description | Example |
|---|---|---|---|---|
| `appid` | string | نعم | معرف حزمة التطبيق (Package Name / Bundle ID) | `com.bassthalk.app` |
| `name` | string | نعم | اسم الجهاز الظاهر | `Samsung Galaxy S23` |
| `model` | string | نعم | طراز الجهاز | `SM-S911B` |
| `platform` | string | نعم | نظام التشغيل | `Android` أو `iOS` |
| `version` | string | نعم | إصدار نظام التشغيل | `14.0` أو `17.4` |
| `pushid` | string | نعم | **رمز الـ FCM Registration Token** المستخرج من فايربيس | `f7A...x9Q` |
| `uuid` | string | نعم | معرف تثبيت الجهاز الفريد (Device UUID) | `9b1deb4d-3b7d-4bad...` |

#### استجابة السيرفر عند النجاح (Success Response):
```json
[]
```
*(مصفوفة فارغة تعني نجاح حفظ وتحديث الجهاز بنجاح).*

---

### ثالثاً: إلغاء تسجيل الجهاز (عند تسجيل الخروج)

عند قيام المستخدم بالضغط على **تسجيل الخروج (Logout)** داخل التطبيق، يجب استدعاء الـ Endpoint التالي لإيقاف وصول الإشعارات لهذا الجهاز:

* **Endpoint:**
  `POST https://<YOUR_DOMAIN>/webservice/rest/server.php`
* **Query Parameters:**
  * `wstoken`: رمز المصادقة الخاص بالمستخدم (قبل مسحه).
  * `wsfunction`: `core_user_remove_user_device`
  * `moodlewsrestformat`: `json`

* **Request Body:**

| Parameter | Type | Required | Description | Example |
|---|---|---|---|---|
| `uuid` | string | نعم | معرف تثبيت الجهاز الفريد | `9b1deb4d-3b7d-4bad...` |
| `appid` | string | لا | اختياري. معرف حزمة التطبيق | `com.bassthalk.app` |

#### استجابة السيرفر عند النجاح (Success Response):
```json
{
  "removed": true,
  "warnings": []
}
```

---

## 4. هيكل الإشعار القادم من السيرفر (Payload Format)

عند إرسال أي إشعار أو رسالة جديدة من المنصة، سيصل إلى التطبيق بالشكل التالي:

```json
{
  "notification": {
    "title": "إشعار جديد",
    "body": "تمت إضافة واجب جديد في كورس الفيزياء."
  },
  "data": {
    "notification_id": "1845",
    "eventtype": "assign_notification",
    "component": "mod_assign",
    "contexturl": "http://localhost:8082/mod/assign/view.php?id=45",
    "timecreated": "1728659100",
    "userfromid": "3",
    "userfromname": "أحمد محمود"
  }
}
```

### تفاصيل الحقول:
1. **`notification.title`**: عنوان الإشعار.
2. **`notification.body`**: نص الإشعار المختصر.
3. **`data.notification_id`**: معرف الإشعار داخل قاعدة بيانات Moodle.
4. **`data.eventtype`**: نوع الحدث (مثل `message`, `assign_notification`, `course_content`, إلخ).
5. **`data.contexturl`**: الرابط المباشر للحدث داخل المنصة (يمكن استخدامه للتوجيه الداخلي Deep Linking).
6. **`data.userfromid`** & **`data.userfromname`**: بيانات المرسل (إن وجد).

### إعدادات القنوات للأندرويد:
* **Channel ID الافتراضي:** `moodle_notifications`
* **Priority:** `HIGH`
* **Click Action:** `FLUTTER_NOTIFICATION_CLICK`

---

## 5. التنظيف الذاتي للرموز (Automatic Stale Token Cleanup)

* لا داعي للقلق بشأن الرموز المنتهية أو المستخدمين الذين قاموا بحذف التطبيق دون تسجيل خروج.
* في حال حاول السيرفر الإرسال لجهاز لم يعد مسجلاً في Firebase (`UNREGISTERED` / `404`)، يقوم السيرفر تلقائياً بحذف رمز هذا الجهاز من قاعدة البيانات لعدم تكرار الإرسال له مستقبلاً.
