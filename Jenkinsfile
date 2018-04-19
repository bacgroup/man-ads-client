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
        sh "mv ../../../openjdk/* ."
        sh "java -version"
        //sh "java -jar packr.jar --platform linux64 --jdk java-1.8.0-openjdk-1.8.0.161-3.b14.el6_9.x86_64.zip --executable OVDNativeClient --classpath OVDNativeClient.jar --mainclass org.ulteo.ovd.client.NativeClient --output OVDNativeClient_linux64"
        archiveArtifacts '*.jar'
    }
    dir("client/OVDIntegratedLauncher"){
        sh "./autogen"
        sh "make"
        archiveArtifacts 'UlteoOVDIntegratedLauncher'
    }
  }
}
