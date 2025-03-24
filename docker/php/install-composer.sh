#!/bin/sh

# Récupérer le checksum attendu depuis l'URL
EXPECTED_CHECKSUM="$(curl -fsSL https://composer.github.io/installer.sig)"

# Télécharger le fichier d'installation de Composer
curl -fsSL https://getcomposer.org/installer -o composer-setup.php

# Calculer le checksum réel avec openssl
ACTUAL_CHECKSUM="$(openssl dgst -sha384 composer-setup.php | awk '{ print $2 }')"

# Comparer les checksums
if [ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]; then
    >&2 echo 'ERROR: Invalid installer checksum'
    rm composer-setup.php
    exit 1
fi

# Exécuter l'installateur Composer
php composer-setup.php --quiet
RESULT=$?

# Nettoyer le fichier installateur
rm composer-setup.php

mv ./composer.phar /usr/local/bin/composer

# Retourner le résultat de l'installation
exit $RESULT
