node("x2go") {
  deleteDir
  checkout scm
  stage("Generate Certificates to Sing Jars") {
      sh "mkdir -p client/java/certificate"
      sh "keytool -genkey -keystore client/java/certificate/keystore -alias ulteo -dname \"CN=manconsulting.com, OU=MAN, O=MAN, L=TGU, S=FM, C=HN\"   -storepass 123456  -keypass 123456"
      sh "keytool -selfcert -keystore client/java/certificate/keystore -alias ulteo -storepass 123456 -keypass 123456"
  }
}
