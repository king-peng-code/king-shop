# React Native
-keep class com.facebook.hermes.unicode.** { *; }
-keep class com.facebook.jni.** { *; }
-keep class com.facebook.react.** { *; }
-keep class com.facebook.fabric.** { *; }

# Fresco
-keep class com.facebook.common.logging.** { *; }
-keep class com.facebook.fresco.** { *; }
-keep class com.facebook.drawee.** { *; }
-keep class com.facebook.imagepipeline.** { *; }

# WeChat SDK
-keep class com.tencent.mm.opensdk.** { *; }

# React Native vector icons
-keep class com.oblador.vectoricons.** { *; }

# Keep custom app classes
-keep class com.kingshop.** { *; }

# Keep R8 from stripping generic signatures
-keepattributes Signature
-keepattributes *Annotation*
