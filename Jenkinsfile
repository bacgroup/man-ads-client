node("x2go") {
  checkout scm
  stage("Build Java Client") {
      sh "keytool -genkey -keystore client/java/certificate/keystore -alias ulteo -dname \"CN=manconsulting.com, OU=MAN, O=MAN, L=TGU, S=FM, C=HN\"   -storepass 123456  -keypass 123456"
  }
}
