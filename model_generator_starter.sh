#! /bin/bash

###################### INCLUDE CONFIG ##################################
SCRIPT_PATH=`dirname $0`
CONFIG_PATH=`readlink -m ${SCRIPT_PATH}/../../config/`
. "${CONFIG_PATH}/LOCAL.sh"
###################### INCLUDE CONFIG ##################################

CURRENT_DIR="$(readlink -m ${SCRIPT_PATH})"
MODEL_ENTITY_TEMPLATE_PATH="${CURRENT_DIR}/templates/ModelEntity.ptpl"
MODEL_TEMPLATE_PATH="${CURRENT_DIR}/templates/Model.ptpl"

MODEL_ENTITY_OUTPUT_PATH="${CURRENT_DIR}/output/ModelEntities"
MODEL_OUTPUT_PATH="${CURRENT_DIR}/output/Models"


# w+ - overwrite if exists
# x+ - don't overwrite if exists. By default.
MG_CONFIG="$(cat <<END_HEREDOC
[
    {
        "template":"${MODEL_ENTITY_TEMPLATE_PATH}", 
        "output":"${MODEL_ENTITY_OUTPUT_PATH}",
        "mode": "w+"
    },
    {
        "template":"${MODEL_TEMPLATE_PATH}",
        "output":"${MODEL_OUTPUT_PATH}",
        "mode": "x+"
    }
]
END_HEREDOC
)"

/usr/bin/php "${SCRIPT_PATH}/model_generator.php" \
            --database-name $APPLICATION_MYSQL_DB_NAME \
            --user $APPLICATION_MYSQL_USER \
            --password $APPLICATION_MYSQL_PASS \
            --host $APPLICATION_HOST \
            --config "$MG_CONFIG" \
            --verbose
STATUS=$?
if [ $STATUS -ne 0 ];then
    echo "Error: "$STATUS
    exit $STATUS;
fi
