import java.util.Properties

plugins {
    id("com.android.application")
    id("com.google.gms.google-services")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

android {
    namespace = "com.jmmart.jmmart"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = flutter.ndkVersion

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
        // Required by flutter_local_notifications.
        isCoreLibraryDesugaringEnabled = true
    }

    defaultConfig {
        // TODO: Specify your own unique Application ID (https://developer.android.com/studio/build/application-id.html).
        applicationId = "com.jmmart.jmmart"
        // You can update the following values to match your application needs.
        // For more information, see: https://flutter.dev/to/review-gradle-config.
        minSdk = flutter.minSdkVersion
        targetSdk = flutter.targetSdkVersion
        // Uses the version code from pubspec.yaml. When using split APKs, 1000 * ABI_VERSION
        // is added automatically by Flutter. (https://developer.android.com/studio/build/configure-apk-splits#configure-APK-versions)
        // You can force using the value of versionCode by specifying the `-P force-version-code-ignoring-abi=true`
        // flag during build.
        versionCode = flutter.versionCode
        versionName = flutter.versionName
        manifestPlaceholders["appLabel"] = "JMMart"
        manifestPlaceholders["oauthScheme"] = "jmmart"
    }

    // `-P pharmacy=true` builds the pharmacy test app beside the live one: its
    // own app ID, name and sign-in link scheme (pair it with OAUTH_SCHEME=jmmartrx
    // in .env.pharmacy). Firebase only knows the live ID, so push is off there.
    if (project.hasProperty("pharmacy")) {
        defaultConfig.applicationIdSuffix = ".pharmacy"
        defaultConfig.manifestPlaceholders["appLabel"] = "JMMart Rx"
        defaultConfig.manifestPlaceholders["oauthScheme"] = "jmmartrx"
    }

    // android/key.properties (never committed) holds storeFile, storePassword, keyAlias, keyPassword.
    val keystoreProperties = Properties().apply {
        val file = rootProject.file("key.properties")
        if (file.exists()) file.inputStream().use { load(it) }
    }

    signingConfigs {
        if (keystoreProperties.containsKey("storeFile")) {
            create("release") {
                storeFile = file(keystoreProperties.getProperty("storeFile"))
                storePassword = keystoreProperties.getProperty("storePassword")
                keyAlias = keystoreProperties.getProperty("keyAlias")
                keyPassword = keystoreProperties.getProperty("keyPassword")
            }
        }
    }

    buildTypes {
        release {
            // Without key.properties the build still runs, signed with the debug key: fine for
            // testing on your own phone, never for a build handed to customers.
            signingConfig = signingConfigs.findByName("release") ?: signingConfigs.getByName("debug").also {
                logger.warn("WARNING: android/key.properties not found; this release build is signed with the DEBUG key.")
            }
        }
    }
}

kotlin {
    compilerOptions {
        jvmTarget = org.jetbrains.kotlin.gradle.dsl.JvmTarget.JVM_17
    }
}

dependencies {
    // Required by flutter_local_notifications's core library desugaring.
    coreLibraryDesugaring("com.android.tools:desugar_jdk_libs:1.2.2")
}

flutter {
    source = "../.."
}

if (project.hasProperty("pharmacy")) {
    tasks.matching { it.name.contains("GoogleServices") }.configureEach { enabled = false }
}
