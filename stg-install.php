<?php
/*
    stg-install.php

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
$version = "v0.0.8";		// extension version
$v = "v0.8.7";			// application version
$appname = "Syncthing Inotify";
$config_name = strtolower(str_replace(" ", "-", $appname));
$version_striped = str_replace(".", "", $version);

require_once("config.inc");

$arch = $g['arch'];
$platform = $g['platform'];
// no check necessary since the extension is for all archictectures/platforms/releases
//if (($arch != "i386" && $arch != "amd64") && ($arch != "x86" && $arch != "x64" && $arch != "rpi" && $arch != "rpi2")) { echo "\f{$arch} is an unsupported architecture!\n"; exit(1);  }
//if ($platform != "embedded" && $platform != "full" && $platform != "livecd" && $platform != "liveusb") { echo "\funsupported platform!\n";  exit(1); }

// install extension
global $input_errors;
global $savemsg;

$install_dir = dirname(__FILE__)."/";                           // get directory where the installer script resides
if (!is_dir("{$install_dir}syncthing-inotify/backup")) { mkdir("{$install_dir}syncthing-inotify/backup", 0775, true); }
if (!is_dir("{$install_dir}syncthing-inotify/update")) { mkdir("{$install_dir}syncthing-inotify/update", 0775, true); }

// check FreeBSD release for fetch options >= 9.3
$release = explode("-", exec("uname -r"));
if ($release[0] >= 9.3) $verify_hostname = "--no-verify-hostname";
else $verify_hostname = "";

// echo "Downloading nas4free-syncthing-inotify-{$version}.tar.gz\n";
$return_val = mwexec("fetch {$verify_hostname} -vo {$install_dir}master.tar.gz 'https://github.com/lhristov/nas4free-syncthing-inotify/releases/download/{$version}/nas4free-syncthing-inotify-{$version}.tar.gz'", true);
if ($return_val == 0) {
    // echo "Extracting {$install_dir}master.tar.gz\n";
    $return_val = mwexec("tar -zxf {$install_dir}master.tar.gz -C {$install_dir} --exclude='.git*' --strip-components 1", true);
    if ($return_val == 0) {
        // echo "Configuring nas4free-syncthing-inotify-{$version}\n";
        exec("rm {$install_dir}master.tar.gz");
        exec("chmod -R 775 {$install_dir}syncthing-inotify");
        require_once("{$install_dir}syncthing-inotify/ext/extension-lib.inc");
        $config_file = "{$install_dir}syncthing-inotify/ext/{$config_name}.conf";
        if (is_file("{$install_dir}syncthing-inotify/version.txt")) { $file_version = exec("cat {$install_dir}syncthing-inotify/version.txt"); }
        else { $file_version = "n/a"; }
        $savemsg = sprintf(gettext("Update to version %s completed!"), $file_version);
    }
    else { $input_errors[] = sprintf(gettext("Archive file %s not found, installation aborted!"), "master.tar.gz corrupt /"); return;}
}
else { $input_errors[] = sprintf(gettext("Archive file %s not found, installation aborted!"), "master.tar.gz"); return;}

// install / update application
if (($configuration = ext_load_config($config_file)) === false) {
    $configuration = array();             // new installation or first time with json config
    $new_installation = true;
}
else $new_installation = false;

// check for $config['syncthing-inotify'] entry in config.xml, convert it to new config file and remove it
if (isset($config[$config_name]) && is_array($config[$config_name])) {
    $configuration = $config[$config_name];								// load config
    unset($config[$config_name]);										// remove old config
}

$configuration['appname'] = $appname;
$configuration['rootfolder'] = "{$install_dir}syncthing-inotify/";
$configuration['backupfolder'] = $configuration['rootfolder']."backup/";
$configuration['updatefolder'] = $configuration['rootfolder']."update/";
$configuration['version'] = exec("cat {$configuration['rootfolder']}version.txt");
$configuration['postinit'] = "/usr/local/bin/php-cgi -f {$configuration['rootfolder']}syncthing-inotify-start.php";
$configuration['shutdown'] = "killall syncthing-inotify";
if ($arch == "i386" || $arch == "x86") { $configuration['architecture'] = "386"; }
else { $configuration['architecture'] = "amd64"; }
$configuration['download_url'] = "https://github.com/syncthing/syncthing-inotify/releases/download/{$v}/syncthing-inotify-freebsd-{$configuration['architecture']}-{$v}.tar.gz";
// echo "Downloading {$configuration['download_url']}\n";
$configuration['previous_url'] = $configuration['download_url'];
$return_val = mwexec ("fetch -o {$configuration['rootfolder']}stable {$configuration['download_url']}", true);
// echo "command is: fetch -o {$configuration['rootfolder']}stable {$configuration['download_url']}\n";
if ($return_val == 0) {
    // echo "syncthing-inotify-freebsd-{$configuration['architecture']}-{$v}.tar.gz downloaded\n";
    // echo "\n";
    $return_val = exec ("cd {$configuration['rootfolder']} && tar -xzf stable"); // --strip-components 1
    // exec ("rm {$configuration['rootfolder']}stable");
    if ($return_val == 0) {
        if ( !is_file ($configuration['rootfolder'].'syncthing-inotify') ) echo 'Executable file "syncthing-inotify" not found!';
        $configuration['product_version'] = $v;
        if (!is_dir ($configuration['rootfolder'].'config')) { exec ("mkdir -p ".$configuration['rootfolder'].'config'); }
        if (!is_dir ($configuration['backupfolder'])) { exec ("mkdir -p ".$configuration['backupfolder']); }
        if (!is_dir ($configuration['updatefolder'])) { exec ("mkdir -p ".$configuration['updatefolder']); }
        exec ("cp ".$configuration['rootfolder']."syncthing-inotify ".$configuration['backupfolder']."syncthing-inotify-".$configuration['product_version']);
        if ($configuration['product_version'] == '') { $configuration['product_version'] = 'n/a'; }

        ext_remove_rc_commands($config_name);
        $configuration['rc_uuid_start'] = $configuration['postinit'];
        $configuration['rc_uuid_stop'] = $configuration['shutdown'];
        ext_create_rc_commands($appname, $configuration['rc_uuid_start'], $configuration['rc_uuid_stop']);
        ext_save_config($config_file, $configuration);

        if ($new_installation) echo "\nInstallation completed, use WebGUI | Extensions | ".$appname." to configure the application!\n";
        else $savemsg = sprintf(gettext("Update to version %s completed!"), $file_version);
        require_once("{$configuration['rootfolder']}syncthing-inotify-start.php");
    } else {
        echo "Error 2";
        return;
    }
} else {
    // $input_errors[] = sprintf(gettext("Archive file %s not found, installation aborted!"), "master.tar.gz corrupt /");
    echo "Error 1";
    return;
}
?>
