# ML Kit text recognition (SC/PWD ID scan) only bundles the Latin reader. Its plugin also
# names the optional Chinese, Devanagari, Japanese and Korean readers, which are left out on
# purpose; without these lines R8 stops the release build on the missing classes.
-dontwarn com.google.mlkit.vision.text.chinese.**
-dontwarn com.google.mlkit.vision.text.devanagari.**
-dontwarn com.google.mlkit.vision.text.japanese.**
-dontwarn com.google.mlkit.vision.text.korean.**
