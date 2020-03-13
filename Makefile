all :
	composer.phar install --no-ansi --no-dev --no-interaction --no-plugins --no-progress --no-scripts --no-suggest --optimize-autoloader &&\
	git archive --prefix mastercard/ -o ./prestashop-mastercard.zip HEAD &&\
        mkdir -p mastercard && mv ./vendor ./mastercard/
	zip -rq ./prestashop-mastercard.zip ./mastercard &&\
        mv ./mastercard/vendor . &&\
        rm -rf ./mastercard &&\
	echo "\nCreated prestashop-mastercard.zip\n"
