# Solana Node Manager

**Instant Hot Swap & Failover Tool for Solana Validators with Enhanced Security**

Solana Node Manager is a simple and secure CLI tool for manual and automatic hot-swapping of a Solana validator identity between two or more servers, ensuring 99.9%+ uptime.  

Keys and server credentials are stored in encrypted form.  
Built in pure PHP with minimal dependencies for maximum security.  

It also provides a safe interface for managing validator transfers via websites or applications, powered by SSH-based command exchange.  

✅ Compatible with **Agave** and **Jito Solana** validators.  

---

## Why Solana Node Manager?

- ⚡ **Instant hot swap** — 0.8 to 3 seconds  
- 🔄 **Automatic failover** with minimal downtime  
- 🌐 Switch between two or more servers  
- 🔑 Validator identity key stored encrypted  
- 🔐 Server access credentials also stored encrypted  
- 📲 Monitoring & alerts via Telegram  
- 🌍 Possible management through a web interface (“Web Solana Node Manager”)  
- 🧪 Tested on both testnet and mainnet  

---

## Installation

### Prerequisites

Each Solana validator server (**active** or **spare**) must contain an unstaked identity file:  

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

All these files are stored inside an **encrypted directory** and loaded into memory only when required by `solana-node-manager`.

---

### Server Requirements

- Separate **VPS/Dedicated server** for Solana Node Manager  
- Ubuntu 22.04/24.04 (with **swap disabled** for security reasons). It is also possible to use other Debian-based distributions (not tested yet).
- Located geographically close to validator servers (to minimize transfer latency)  

---

### Installation Steps

```bash
sudo apt-get update && sudo apt-get upgrade && sudo apt-get install git
git clone https://github.com/StakeNode777/solana-node-manager
bash ~/solana-node-manager/install.sh
```

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

1. Create a new bot with **@BotFather** by sending the command `/newbot` and save the token  
   (looks like `123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11`).  

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

## Contributing

Contributions are welcome!  
Please open an [issue](https://github.com/StakeNode777/solana-node-manager/issues) or submit a pull request if you want to improve this project.

---

## License

This project is licensed under the [MIT License](LICENSE).

---
