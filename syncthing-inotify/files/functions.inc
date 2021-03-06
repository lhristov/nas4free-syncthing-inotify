<?php
/*
    functions.inc

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

function stg_call($input, &$return_val = NULL) {
    global $configuration;
    global $savemsg;

    $o = exec("su root -c '{$configuration['rootfolder']}{$input}'", $output, $return_val);
    return $o;
}

// save backup from auto-upgrade to backup folder and renew product_version
if (is_file("{$configuration['rootfolder']}syncthing-inotify.old")) {
    $version_old = stg_call("syncthing-inotify.old -version");
    $v = explode(" ", $version_old);
    mwexec("mv -v {$configuration['rootfolder']}syncthing-inotify.old {$configuration['backupfolder']}syncthing-inotify-{$v[1]}", true);
    $savemsg .= sprintf(gettext("Syncthing Inotify version %s has been backuped!"), $v[1])." ";
    exec("logger syncthing: Syncthing Inotify version {$v[1]} has been backuped!");
    $configuration['product_version'] = stg_call("syncthing-inotify -version");
	ext_save_config($config_file, $configuration);
}

?>
