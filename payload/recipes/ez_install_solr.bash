#!/usr/bin/env bash

PROVISIONING=$1
ACTION=$2

DESTINATION_EZ="/ezsolr/server/ez"
DESTINATION_TEMPLATE="$DESTINATION_EZ/template"
PHP="php"

if [ $ACTION == "COMPOSER_INSTALL" ]; then
    # it is run on the engine
    cd $PROJECTMAPPINGFOLDER/ezplatform
    mkdir -p $DESTINATION_TEMPLATE
    cp -R vendor/ezsystems/ezplatform-solr-search-engine/lib/Resources/config/solr/* $DESTINATION_TEMPLATE
    # simplest way to allow solr to add the conf here... from its own container
    # We could do better by extending the Dockerfile and build.. but it is also less "generic"
    chmod -R 777 $DESTINATION_EZ
fi

if [ $ACTION == "INDEX" ]; then
    # it is run on the engine
    until wget -q -O - http://solr:8983 | grep -q -i solr; do
        echo -n "."
        sleep 2
    done
    # wait cores
    sleep 5
    echo "Solr is running"
    cd $PROJECTMAPPINGFOLDER/ezplatform
    CONSOLE="bin/console"
    if [ -f app/console ]; then
        CONSOLE="app/console"
    fi
    $PHP $CONSOLE --env=prod ezplatform:reindex
fi

if [ $ACTION == "CREATE_CORE" ]; then
    # it is run on the solr
    until wget -q -O - http://localhost:8983 | grep -q -i solr; do
        echo -n "."
        sleep 2
    done
    echo "Solr is running"

    SOLR_CORES=${SOLR_CORES:-collection1}
    for core in $SOLR_CORES
    do
        /opt/solr/bin/solr create_core -c ${core} -d $DESTINATION_TEMPLATE
        echo "Core ${core} created."
    done

fi

