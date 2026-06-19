# flutter_stripe push provisioning classes are optional (only needed for Apple Pay provisioning)
-dontwarn com.stripe.android.pushProvisioning.**
-keep class com.stripe.android.pushProvisioning.** { *; }
