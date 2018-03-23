node("x2go") {
  checkout scm
  stage("Build Java Client") {
      sh "rm -rf /home/trimarchi/ovd/client/java/certificate/keystore || True"
      sh "keytool -genkey -keystore /home/trimarchi/ovd/client/java/certificate/keystore -alias ulteo -dname "CN=manconsulting.com, OU=MAN, O=MAN, L=TGU, S=FM, C=HN"   -storepass 123456  -keypass 123456"
  }
}
