#!/bin/bash

#Installation script for
#Solana Node Manager


INSTALL_DIR=$(dirname "$(realpath "$0")")


function inputKeypair() {
	KEYPAIR_FILE="${1}"
	while true; do
		echo -n "Please input content of ${KEYPAIR_FILE}: "
		KEYPAIR=""
		while IFS= read -r -s -n1 char; do
			if [[ $char == $'\0' ]]; then
				break
			fi
			if [[ $char == $'\x7f' ]]; then  # Handle backspace
				if [ -n "$KEYPAIR" ]; then
					KEYPAIR="${KEYPAIR%?}"
					echo -ne "\b \b"  # Move back and erase
				fi
			else
				KEYPAIR+="$char"
				echo -n '*'
			fi
		done
		echo

		echo "${KEYPAIR}" > "${KEYPAIR_FILE}"
		PUBKEY=$(solana-keygen pubkey "${KEYPAIR_FILE}")

		echo "Please check public key."
		read -p "Is it '${PUBKEY}'? (y/n)" ANSWER
		case "$ANSWER" in
		    [yY]) break ;;
		    [nN]) ;;
		    *) echo "Invalid input. Please, enter y or n." ;;
		esac
	done
}

cd $HOME

IDENTITY_FILE="${PWD}/config/validator-keypair.json"

if [ -d "config" ]; then
    echo -e "\n\nSEEMS ALREADY INSTALLED.\nPlease remove folders 'config', 'config.enc' to reinstall\n\n"
	exit;
fi

sudo apt-get update && sudo apt-get upgrade -y
sudo apt install -y curl gocryptfs nano php php-curl
read -p "Solana version: " SOLANA_VERSION
sh -c "$(curl -sSfL https://release.anza.xyz/v${SOLANA_VERSION}/install)"
export PATH="${HOME}/.local/share/solana/install/active_release/bin:$PATH"
echo "Creating logs dir..."
mkdir logs

echo "Creating encrypted folder for config and keypairs...\n"
mkdir config.enc && mkdir config && gocryptfs -init "${PWD}/config.enc"  &&  gocryptfs -idle "600s" "${PWD}/config.enc" "${PWD}/config"


echo " 

Please input content of the config.txt file.
Please refer to config.conf.sample
or to https://github.com/StakeNode777/solana-node-manager/config.conf.sample

"


read -p "Press ENTER to continue " SOME_EMPTY_VAR

nano ${PWD}/config/config.conf

inputKeypair "${IDENTITY_FILE}"

fusermount -u "${PWD}/config"


cat >start_snm.sh <<EOF
#!/bin/bash

rerun_php_proc() {
    local file="\$1"

    echo -e "\nKILL OLD PROCESSES for '\$file'\n"

    # search PIDs of processes for current user
    pids=\$(pgrep -u "\$USER" -f "\$file")

    if [[ -z "\$pids" ]]; then
        echo "No processes found  with '\$file' for the \$USER"
    else
        for pid in \$pids; do
            kill "\$pid" && echo "#\$pid killed"
        done
    fi
}

rerun_php_proc "snm_auto_transfer.php"
rerun_php_proc "snm_file_cmd.php"

echo "Finishing old ones"

echo "Starting solana_auto_transfer_manager..."

gocryptfs -idle "15s" "${PWD}/config.enc" "${PWD}/config"

nohup php $INSTALL_DIR/snm_auto_transfer.php --config_dir=$PWD/config >> $PWD/logs/snm_auto_transfer.log 2>&1 &
nohup php $INSTALL_DIR/snm_file_cmd.php --config_dir=$PWD/config >> $PWD/logs/snm_file_cmd.log 2>&1 &

sleep 5

fusermount -u "${PWD}/config"

EOF


CONFIG_UNLOCK_PART="S_DIR0=$HOME/config.enc
S_DIR=$HOME/config
if [ -z \"\$(ls -A \"\$S_DIR\")\" ]; then
  echo \"\"
  echo \"Try to open encrypted folder config. Could ask password ...\"
  echo \"\"
  gocryptfs -idle \"600s\" \$S_DIR0 \$S_DIR
  
fi"

CONFIG_LOCK_INFO_TEXT="\"Encrypted folder config is opened for 10 min.
Don't forget to run this command after changes:

fusermount -u '${PWD}/config'\"
"


cat >transfer_safe.sh <<EOF
#!/bin/bash

$CONFIG_UNLOCK_PART

php $INSTALL_DIR/transfer_node.php --config_dir='$PWD/config' --mode='safe'

echo $CONFIG_LOCK_INFO_TEXT

EOF


cat >activate_safe.sh <<EOF
#!/bin/bash

$CONFIG_UNLOCK_PART

php $INSTALL_DIR/transfer_node.php --config_dir='$PWD/config' --mode='safe_activate'

echo $CONFIG_LOCK_INFO_TEXT

EOF



cat >test_config.sh <<EOF
#!/bin/bash

$CONFIG_UNLOCK_PART

php $INSTALL_DIR/test_config.php --config_dir='$PWD/config'

echo $CONFIG_LOCK_INFO_TEXT

EOF



cat >unlock_config.sh <<EOF
#!/bin/bash

$CONFIG_UNLOCK_PART

echo "The encrypted folder 'config' is unlocked for 10 minutes.
You can change to the next files now:
config.conf - there are configuration of your node servers and other significant params for solana-node-manager
validator-keypair.json - identity key of your validator

You can test your configuration by this command:
bash test_config.sh

Also don't forget to run this command after changes to lock the config dir back:

fusermount -u \"${PWD}/config\"
"

EOF

echo "Installation completed. 
To start solana-node-manager please run the following command: 

bash start_snm.sh

"