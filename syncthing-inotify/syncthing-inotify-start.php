<?php
/*
    syncthing-inotify-start.php

    Copyright (c) 2013 - 2017 Andreas Schmidhuber <info@a3s.at>
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice, this
       list of conditions and the following disclaimer.
    2. Redistributions in binary form must reproduce the above copyright notice,
       this list of conditions and the following disclaimer in the documentation
       and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
    ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
    WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
    DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
    ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
    (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
    LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
    ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
    (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
    SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */
require_once("config.inc");

$rootfolder = dirname(__FILE__)."/";
$config_file = "{$rootfolder}ext/syncthing-inotify.conf";
require_once("{$rootfolder}ext/extension-lib.inc");
if (($configuration = ext_load_config($config_file)) === false) {
    exec("logger syncthing-inotify-extension: configuration file {$config_file} not found, startup aborted!");
    exit;
}

if (is_file("{$configuration['rootfolder']}version.txt")) {
    $file_version = exec("cat {$configuration['rootfolder']}version.txt");
    if ($configuration['version'] != $file_version) {
        $configuration['version'] = $file_version;
		ext_save_config($config_file, $configuration);
    }
}

// check for ca_root_certificate and create link if not exists (Syncthing needs it now ...)
if (!is_file("/usr/local/share/certs/ca-root-nss.crt")) {
    mwexec("mkdir -p /usr/local/share/certs", false);
    if (is_file("/usr/local/etc/ssl/cert.pem")) {
        mwexec("ln -s /usr/local/etc/ssl/cert.pem /usr/local/share/certs/ca-root-nss.crt", false);
        exec("logger syncthing-inotify-extension: cert.pem exists, create link ...");
    }
    else {
        mwexec("ln -s {$configuration['rootfolder']}files/cert.pem /usr/local/share/certs/ca-root-nss.crt", false);
        exec("logger syncthing-inotify-extension: cert.pem doesn't exist, use own certificate ...");
    }
}

// save backup from auto-upgrade to backup folder and renew product_version
if (is_file("{$configuration['rootfolder']}syncthing-inotify.old")) {
    $version_old = exec("{$configuration['rootfolder']}syncthing-inotify.old -version | awk '{print $2}'");
    mwexec("mv -v {$configuration['rootfolder']}syncthing-inotify.old {$configuration['backupfolder']}syncthing-inotify-{$version_old}", true);
    exec("logger syncthing-inotify-extension: Syncthing version {$version_old} has been backuped!");
    $configuration['product_version'] = exec("{$configuration['rootfolder']}syncthing-inotify -version");
	ext_save_config($config_file, $configuration);
}

if (is_dir("/usr/local/www/ext/syncthing-inotify")) mwexec("rm -R /usr/local/www/ext/syncthing-inotify");	// cleanup of previous versions < v0.2.x
$return_val = 0;
// create links to extension files
$return_val += mwexec("mkdir -p /usr/local/www/ext");					// if it is the first extension we need this directory
$return_val += mwexec("ln -sfw {$rootfolder}ext /usr/local/www/ext/syncthing-inotify", true);
$return_val += mwexec("ln -sfw {$rootfolder}locale-stg /usr/local/share/", true);
$return_val += mwexec("ln -sfw {$rootfolder}ext/syncthing-inotify.php /usr/local/www/syncthing-inotify.php", true);
$return_val += mwexec("ln -sfw {$rootfolder}ext/syncthing_inotify_log.php /usr/local/www/syncthing_inotify_log.php", true);
$return_val += mwexec("ln -sfw {$rootfolder}ext/syncthing_inotify_log.inc /usr/local/www/syncthing_inotify_log.inc", true);
$return_val += mwexec("ln -sfw {$rootfolder}ext/syncthing_inotify_update.php /usr/local/www/syncthing_inotify_update.php", true);
$return_val += mwexec("ln -sfw {$rootfolder}ext/syncthing_inotify_update_extension.php /usr/local/www/syncthing_inotify_update_extension.php", true);
if ($return_val != 0) mwexec("logger syncthing-inotify-extension: error during startup, link creation failed with return value = {$return_val}");
else if ($configuration['enable']) {
	    mwexec("killall syncthing-inotify");
		$check_hour = date("G");
	    if ($configuration['enable_schedule'] && $configuration['schedule_prohibit'] && (($check_hour < $configuration['schedule_startup']) || ($check_hour >= $configuration['schedule_closedown']))) {
			mwexec("logger syncthing-inotify-extension: Syncthing start prohibited due to scheduler settings!");
		}
	    else {
		    mwexec("logger syncthing-inotify-extension: enabled, start syncthing-inotify ...");
		    exec($configuration['command']);
		    sleep(5);														// give time to startup
		    if (exec('ps acx | grep syncthing-inotify')) { mwexec("logger syncthing-inotify-extension: startup OK"); }
		    else { mwexec("logger syncthing-inotify-extension: startup NOT ok" ); }
		}
	}
?>
