node("manukbuild001") {
  deleteDir()
  checkout scm
  stage("Create Certificates") {
      sh "mkdir -p client/java/certificate"
      sh "keytool -genkey -keystore client/java/certificate/keystore -alias ulteo -dname \"CN=manconsulting.co.uk, OU=MAN, O=MAN, L=UK, S=UK, C=UK\"   -storepass 123456  -keypass 123456"
      sh "keytool -selfcert -keystore client/java/certificate/keystore -alias ulteo -storepass 123456 -keypass 123456"
  }
  stage("Build") {
    dir("client/java/") {
        sh "./autogen"
        sh "ant ovdNativeClient.jar"
        sh "ant ovdIntegratedLauncher.jar"
    }
    dir("client/java/jars") {
        sh "java -jar ../../../openjdk/packr.jar --platform windows32 --jdk ../../../openjdk/openjdk-1.7.0-u80-unofficial-windows-i586-image.zip --executable OVDNativeClient --classpath OVDNativeClient.jar --removelibs OVDNativeClient.jar --mainclass org.ulteo.ovd.client.NativeClient --minimizejre soft --output OVDNativeClient_windows32"
        archiveArtifacts '*.jar'
    }
    dir("client/OVDIntegratedLauncher"){
        sh "./autogen"
        sh "make"
        archiveArtifacts 'UlteoOVDIntegratedLauncher'
    }
  }
}
