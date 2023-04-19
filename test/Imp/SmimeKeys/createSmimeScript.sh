#! /bin/sh

#umask 077

# $user is used for the subdir to hold the certs AND for
# the certificate's Common Name
user="$1"
mkdir "./$user"

#umask 277

KEY="$user/cert.key"
CSR="$user/cert.csr"
CERT="$user/cert.crt"
P12="$user/cert.p12"

# certificate details for herenow script (configurable)
COUNTRY="DE"                    # 2 letter country-code
STATE="Sachsen"                 # state or province name
LOCALITY="Dresden"              # Locality Name (e.g. city)
ORGNAME="organisation name"     # Organization Name (eg, company)
ORGUNIT="Development"           # Organizational Unit Name (eg. section)
EMAIL="$user@dev.localhost"     # certificate's email address

# optional extra details
CHALLENGE="bullshit123"     # challenge password
COMPANY=""                  # company name

# generating key
openssl genrsa -des3 -out $KEY 4096


# create the csr
cat <<__EOF__ | openssl req -new -key $KEY -out $CSR
$COUNTRY
$STATE
$LOCALITY
$ORGNAME
$ORGUNIT
$user
$EMAIL
$CHALLENGE
$COMPANY
__EOF__

# Sing the key
openssl x509 -req -days 365 -in $CSR -signkey $KEY -out $CERT

# Converting to pkcs12
openssl pkcs12 -export -in $CERT -inkey $KEY -name "$user" -out $P12

