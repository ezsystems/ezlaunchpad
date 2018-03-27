#!/usr/bin/env bash

mkdir -p /ezsolr/server/ez
if [ ! -f /ezsolr/server/ez/solr.xml ]; then
    cp /opt/solr/server/solr/solr.xml /ezsolr/server/ez
    cp /opt/solr/server/solr/configsets/basic_configs/conf/{currency.xml,solrconfig.xml,stopwords.txt,synonyms.txt,elevate.xml} /ezsolr/server/ez/template
    sed -i.bak '/<updateRequestProcessorChain name="add-unknown-fields-to-the-schema">/,/<\/updateRequestProcessorChain>/d' /ezsolr/server/ez/template/solrconfig.xml
    sed -i -e 's/<maxTime>${solr.autoSoftCommit.maxTime:-1}<\/maxTime>/<maxTime>${solr.autoSoftCommit.maxTime:20}<\/maxTime>/g' /ezsolr/server/ez/template/solrconfig.xml
fi

chmod 777 /ezsolr/server/ez/collection1

/opt/solr/bin/solr -s /ezsolr/server/ez -f
/opt/solr/bin/solr create_core -c collection1 -d /ezsolr/server/ez/template
