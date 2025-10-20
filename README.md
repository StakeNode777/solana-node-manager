# Solana Node Manager

**Instant Hot Swap & Failover Tool for Solana Validators with Enhanced Security**

Solana Node Manager (SNM) is a simple and secure CLI tool for manual and automated hot-swapping of Solana validator identities between two or more servers, ensuring **99.9%+ uptime**.  

All keys and server credentials are stored in encrypted form, preventing unauthorized access in rescue mode or after reboot.  
SNM is built in pure PHP with minimal dependencies to maximize transparency and security.  

The tool is shipped with pre-bundled vendor content to mitigate supply-chain attacks.  

It also provides a secure interface for managing validator transfers via websites or applications, powered by SSH-based command exchange.  

✅ Fully compatible with **Agave** and **Jito-Solana** validators. 

---

## Why Solana Node Manager?

- ⚡ **Instant hot swap** — 0.8 to 3 seconds  
- 🔄 **Automatic failover** with minimal downtime  
- 🌐 Switch between two or more servers  
- 🛡️ Focus on source code **security and transparency**
- 🔑 **Encrypted** validator identity and server credentials   
- 🧠 Simple configuration and CLI-based operation
- 📲 Monitoring & alerts via Telegram  
- 🌍 Possible management through a web interface (“Web Solana Node Manager”)  
- 🧪 Tested on both testnet and mainnet  

---

## Installation

### Prerequisites

There are only a few requirements for each Solana validator server:

- Only one instance of the **agave-validator** process should be running  
- The `--vote-account` parameter must contain a valid public key  
- The server must have an unstaked identity file located in the home directory at **~/unstaked-identity.json**. To create it, use the following command:

```bash
solana-keygen new -s --no-bip39-passphrase -o ~/unstaked-identity.json
```

### Configuration

During setup, you will need to provide:

- **Configuration file** — see [`config.conf.sample`](https://github.com/StakeNode777/solana-node-manager/config.conf.sample).  
  It contains:  
  
  - credentials for validator servers  
  - cluster information  
  - emulation/real mode  
  - public identity and vote keys  
  - and other settings  

- **Validator identity key** (`validator-keypair.json`)  

All these files are stored inside an **encrypted directory**. When running, Solana Node Manager loads them into memory for the duration of its operation.

---

### Server Requirements

- Separate VPS/Dedicated server for Solana Node Manager  
- Ubuntu 22.04/24.04. It is also possible to use other Debian-based distributions (not tested yet).
- Highly recommended to **disable swap** for security reasons — SNM keeps keys and config in memory in certain modes, and they should never be written to disk.
- Highly recommended to locate geographically close to validator servers (to minimize transfer latency)  

---

### Installation steps

```bash
sudo apt update && sudo apt upgrade -y && sudo apt install -y git
git clone https://github.com/StakeNode777/solana-node-manager
bash ~/solana-node-manager/install.sh
```

> 💡 For improved security, consider installing under a non-root user with sudo privileges.

---

### Web Solana Node Manager (Optional)

To enable web-based management, create a dedicated user and request directory:  

```bash
sudo adduser wsnm
sudo mkdir -p /home/wsnm/snm_request_dir
sudo chmod 777 /home/wsnm/snm_request_dir
```

> 💡 For better security, use `chmod 770` with a proper group instead of `777`.

---

### Telegram Alerts Setup (Optional)

To enable Telegram notifications:

1. Create a new bot with [@BotFather](https://t.me/botfather) by sending the command `/newbot` and save the generated token (looks like `123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11`). 

2. Create a group, add your bot as an **administrator**, and send at least one message in the group.  

3. Visit:  https://api.telegram.org/bot<YOUR_BOT_TOKEN>/getUpdates . Find your **chat ID** in the response (negative for groups, positive for private chats).  

4. Add the bot token and chat ID to your `config/config.conf` file (unlock it first with `bash unlock_conf.sh`) under the variables:  
   
   ```ini
   TG_TOKEN=<your_bot_token>
   TG_CHAT_ID=<your_chat_id>
   ```

5. Restart Solana Node Manager in failover mode:

`bash start_snm.sh`

---

### Multiple Configurations (Optional)

We recommend installing Solana Node Manager separately for each new validator.  
However, it is also possible to run multiple Solana Node Manager instances on the same server.  
To do this, simply create another user and repeat the installation steps.

## Usage

Solana Node Manager can run in **failover mode** (automatically monitoring and switching validators if one server goes down) and/or in **manual mode** (hot-swapping initiated by the operator).  

It also supports command execution via the optional web interface, if enabled.  

Main scripts:  

- `bash start_snm.sh` — starts Solana Node Manager in failover mode and enables SSH command handling (if web interface is configured).  It continuously monitors your validator nodes and performs **instant hot swap** when detect problems on the active server.
- `bash unlock_conf.sh` — unlocks the encrypted configuration folder (`config.conf` and `validator-keypair.json`).  
- `bash test_conf.sh` — validates configuration and server connections.  
- `bash transfer_safe.sh` — performs a manual hot swap with pre-checks.  
- `bash activate_safe.sh` — activates a spare validator node (useful if no active node is currently running).  

---

## Key Features

- **Fast Hot Swap:** 0.8 to 3 seconds hot swap operations  
- **Automated Failover:** automatic failover when the primary validator goes down  
- **Security and Transparency:** built in pure PHP with minimal dependencies and fully auditable code. The tool is shipped with pre-bundled vendor content to mitigate supply-chain attacks
- **Encrypted Sensitive Data:** validator key and SSH access to validator servers are stored in encrypted form and loaded into memory only during runtime
- **Up to 9 spare servers:** easily switch between up to 10 configured servers  
- **Hot Swap Compatibility:** supports both Agave and Jito validators  
- **Telegram Alerts:** receive alerts about problems on main/spare servers, failover success/failure, and SNM health status  
- **Safe Web Interface:** possible safe management through “Web Solana Node Manager” GUI  

---

## Contributing

Contributions are welcome!  
Please open an [issue](https://github.com/StakeNode777/solana-node-manager/issues) or submit a pull request if you want to improve this project.

---

## License

This project is licensed under the [MIT License](LICENSE).

---
