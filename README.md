# ROIwoocommerce
A WooCommerce extension for accepting ROI Coin

## Dependancies
This plugin is rather simple but there are a few things that need to be set up before hand.

* A web server! Ideally with the most recent versions of PHP and mysql

* The ROI Coin wallet-cli and ROI Coin wallet-rpc tools found [here](https://github.com/ROIcoin/ROIcoin/releases)

* [WordPress](https://wordpress.org)
Wordpress is the backend tool that is needed to use WooCommerce and this ROI Coin plugin

* [WooCommerce](https://woocommerce.com)
This ROI Coin plugin is an extension of WooCommerce, which works with WordPress

## Step 1: Activating the plugin
* Downloading: First of all, you will need to download the plugin. You can download the latest release as a .zip file from https://github.com/ If you wish, you can also download the latest source code from GitHub. This can be done with the command `git clone https://github.com/pwhelan/sumowp.git` or can be downloaded as a zip file from the GitHub web page.

* Unzip the file ROIcoinwp_release.zip if you downloaded the zip from the releases page [here](https://github.com/).

* Put the plugin in the correct directory: You will need to put the folder named `ROIcoin` from this repo/unzipped release into the wordpress plugins directory. This can be found at `path/to/wordpress/folder/wp-content/plugins`

* Activate the plugin from the WordPress admin panel: Once you login to the admin panel in WordPress, click on "Installed Plugins" under "Plugins". Then simply click "Activate" where it says "ROIcoin - WooCommerce Gateway"

## Step 2: Get a ROI Coin daemon to connect to

### Option 1: Running a full node yourself

To do this: start the ROI Coin daemon on your server and leave it running in the background. This can be accomplished by running `./roicoind` inside your ROI Coin downloads folder under /Resources/bin. The first time that you start your node, the ROI Coin daemon will download and sync the entire ROI Coin blockchain. This can take several hours and is best done on a machine with at least 4GB of ram, an SSD hard drive (with at least 15GB of free space), and a high speed internet connection.

### Option 2: Connecting to a remote node
[[TODO]]

## Step 3: Setup your ROI Coin wallet-rpc

* Setup a ROI Coin wallet using the roicoin-wallet-cli tool. If you do not know how to do this you can learn about it at [roi-coin.com](https://roi-coin.com)

* Start the Wallet RPC and leave it running in the background. This can be accomplished by running `./roicoin-wallet-rpc --rpc-bind-port 18082 --rpc-login username:password --log-level 2 --wallet-file /path/walletfile` where "username:password" is the username and password that you want to use, seperated by a colon and  "/path/walletfile" is your actual wallet file. If you wish to use a remote node you can add the `--daemon-address` flag followed by the address of the node. `--daemon-address seed1.roi-coin.com` for example.

## Step 4: Setup ROI Coin Gateway in WooCommerce

* Navigate to the "settings" panel in the WooCommerce widget in the WordPress admin panel.

* Click on "Checkout"

* Select "ROI Coin GateWay"

* Check the box labeled "Enable this payment gateway"

* Enter your ROI Coin wallet address in the box labled "ROI Coin Address". If you do not know your address, you can run the `address` commmand in your ROI Coin wallet

* Enter the IP address of your server in the box labeled "ROI Coin wallet rpc Host/IP"

* Enter the port number of the Wallet RPC in the box labeled "ROI Coin wallet rpc port" (will be `18082` if you used the above example).

* Enter the username and password that you want to use in their respective feilds

* Click on "Save changes"

## Info on server authentication
It is reccommended that you specify a username/password with your wallet rpc. This can be done by starting your wallet rpc with `roicoin-wallet-rpc --rpc-bind-port 18082 --rpc-login username:password --wallet-file /path/walletfile` where "username:password" is the username and password that you want to use, seperated by a colon. Alternatively, you can use the `--restricted-rpc` flag with the wallet rpc like so `./roicoin-wallet-rpc --testnet --rpc-bind-port 18082 --restricted-rpc --wallet-file wallet/path`.

## Donate to the ROI Coin devs
ROI Address : `RSB4kRvi9qZs4mSwdKdrvDAkG5xLqm7p5C`
