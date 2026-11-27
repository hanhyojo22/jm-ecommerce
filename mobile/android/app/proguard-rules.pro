# ML Kit text recognition (SC/PWD ID scan) only bundles the Latin reader. Its plugin also
# names the optional Chinese, Devanagari, Japanese and Korean readers, which are left out on
# purpose; without these lines R8 stops the release build on the missing classes.
-dontwarn com.google.mlkit.vision.text.chinese.**
-dontwarn com.google.mlkit.vision.text.devanagari.**
-dontwarn com.google.mlkit.vision.text.japanese.**
-dontwarn com.google.mlkit.vision.text.korean.**

# Medicine reminders: flutter_local_notifications saves scheduled alarms as JSON with Gson and
# reads them back through a TypeToken. R8 strips the generic signature that TypeToken needs, so
# every alarm after the first failed with "Missing type parameter." Keep Gson's reflection data
# and the plugin's saved models (their field names are the stored JSON keys).
-keepattributes Signature
-keepattributes *Annotation*
-keep,allowobfuscation,allowshrinking class com.google.gson.reflect.TypeToken
-keep,allowobfuscation,allowshrinking class * extends com.google.gson.reflect.TypeToken
-keep class * extends com.google.gson.TypeAdapter
-keep class * implements com.google.gson.TypeAdapterFactory
-keep class * implements com.google.gson.JsonSerializer
-keep class * implements com.google.gson.JsonDeserializer
-keep class com.dexterous.flutterlocalnotifications.** { *; }
