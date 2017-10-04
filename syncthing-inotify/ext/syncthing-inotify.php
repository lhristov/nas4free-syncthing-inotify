<?php
/*
    syncthing-inotify.php

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
require("auth.inc");
require("guiconfig.inc");

bindtextdomain("nas4free", "/usr/local/share/locale-stg");

$config_file = "ext/syncthing-inotify/syncthing-inotify.conf";
require_once("ext/syncthing-inotify/extension-lib.inc");
if (($configuration = ext_load_config($config_file)) === false) $input_errors[] = sprintf(gettext("Configuration file %s not found!"), "syncthing-inotify.conf");
if (!isset($configuration['rootfolder']) && !is_dir($configuration['rootfolder'] )) $input_errors[] = gettext("Extension installed with fault");
require_once("{$configuration['rootfolder']}files/xml_converter.php");
require_once("{$configuration['rootfolder']}files/functions.inc");

// Dummy standard message gettext calls for xgettext retrieval!!!
$dummy = gettext("The changes have been applied successfully.");
$dummy = gettext("The configuration has been changed.<br />You must apply the changes in order for them to take effect.");
$dummy = gettext("The following input errors were detected");

define("GLOBALASERVER", "default");

$pgtitle = array(gettext("Extensions"), $configuration['appname']." ".$configuration['version']);

function get_syncthing_home($process = 'syncthing') {
    if (exec("ps ax | grep {$process}", $out) && is_array($out)) { // syncthing is running
        preg_match_all('/-home ([^$ ]*)/', implode("\n", $out), $matches, PREG_SET_ORDER, 0);
        if(isset($matches[0]) && isset($matches[0][1])) {
            if(is_dir(trim($matches[0][1]))) {
                return trim($matches[0][1]);
            }
            return "";
        }
    } else {
        return "";
    }
}

function get_syncthing_options($path) {
    if (is_file($path)) {
        $syncthing_conf = XML2Array::createArray(file_get_contents($path));
    } else {
        $syncthing_conf = array();
    }
    return $syncthing_conf;
}

function get_process_info($process = 'syncthing-inotify') {
    if (exec("ps acx | grep {$process}")) { $state = '<a style=" background-color: #00ff00; ">&nbsp;&nbsp;<b>'.gettext("running").'</b>&nbsp;&nbsp;</a>'; }
    else { $state = '<a style=" background-color: #ff0000; ">&nbsp;&nbsp;<b>'.gettext("stopped").'</b>&nbsp;&nbsp;</a>'; }
	return ($state);
}

/* Check if the directory exists, the mountpoint has at least o=rx permissions and
 * set the permission to 775 for the last directory in the path
 */
function change_perms($dir) {
    global $input_errors;

    $path = rtrim($dir,'/');                                            // remove trailing slash
    if (strlen($path) > 1) {
        if (!is_dir($path)) {                                           // check if directory exists
            $input_errors[] = sprintf(gettext("Directory %s doesn't exist!"), $path);
        }
        else {
            $path_check = explode("/", $path);                          // split path to get directory names
            $path_elements = count($path_check);                        // get path depth
            $fp = substr(sprintf('%o', fileperms("/$path_check[1]/$path_check[2]")), -1);   // get mountpoint permissions for others
            if ($fp >= 5) {                                             // transmission needs at least read & search permission at the mountpoint
                $directory = "/$path_check[1]/$path_check[2]";          // set to the mountpoint
                for ($i = 3; $i < $path_elements - 1; $i++) {           // traverse the path and set permissions to rx
                    $directory = $directory."/$path_check[$i]";         // add next level
                    exec("chmod o=+r+x \"$directory\"");                // set permissions to o=+r+x
                }
                $path_elements = $path_elements - 1;
                $directory = $directory."/$path_check[$path_elements]"; // add last level
                exec("chmod 775 {$directory}");                         // set permissions to 775
                exec("chown {$_POST['who']} {$directory}*");
            }
            else
            {
                $input_errors[] = sprintf(gettext("Syncthing Inotify needs at least read & execute permissions at the mount point for directory %s! Set the Read and Execute bits for Others (Access Restrictions | Mode) for the mount point %s (in <a href='disks_mount.php'>Disks | Mount Point | Management</a> or <a href='disks_zfs_dataset.php'>Disks | ZFS | Datasets</a>) and hit Save in order to take them effect."), $path, "/{$path_check[1]}/{$path_check[2]}");
            }
        }
    }
}

if (isset($_POST['save']) && $_POST['save']) {
    unset($input_errors);
//    $pconfig = $_POST;
	if (empty($input_errors)) {
		if (isset($_POST['enable'])) {

            $configuration['syncthing_extension_path'] = !empty($_POST['syncthing_extension_path']) ? $_POST['syncthing_extension_path'] : get_syncthing_home();

            $syncthing_conf = get_syncthing_options($configuration['syncthing_extension_path']);

            $configuration['enable'] = isset($_POST['enable']);
            $configuration['who'] = $_POST['who'];

            $configuration['api_key'] = !empty($_POST['api_key']) ? $_POST['api_key'] : (is_array($syncthing_conf) && !empty($syncthing_conf['configuration']['gui']['apikey']) ? $syncthing_conf['configuration']['gui']['apikey'] : "");

            $ip = "127.0.0.1";
            $port = "8384";
            if(is_array($syncthing_conf) && !empty($syncthing_conf['configuration']['gui']['address'])) {
                $address = explode(":", $syncthing_conf['configuration']['gui']['address']);
                $ip = $address[0];
                $port = $address[1];
            }
            $configuration['syncthing_ip'] = !empty($_POST['syncthing_ip']) ? $_POST['syncthing_ip'] : $ip;
            $configuration['syncthing_port'] = !empty($_POST['syncthing_port']) ? $_POST['syncthing_port'] : $port;


            $api_parameter = !empty($configuration['api_key']) ? "-api=" . $configuration['api_key'] : "";

            $syncthing_ip = '-target="https://' . $ip . ":" . $port . '"';

    		$configuration['command'] = "su {$configuration['who']} -c '{$configuration['rootfolder']}syncthing-inotify {$syncthing_ip} {$api_parameter} > {$configuration['rootfolder']}syncthing-inotify.log & '";

            exec("killall syncthing-inotify");
            $return_val = 0;
            while( $return_val == 0 ) { sleep(1); exec('ps acx | grep syncthing-inotify', $output, $return_val); }
            unset ($output);
            exec($configuration['command'], $output, $return_val);
            if ($return_val != 0) { $input_errors = $output; }
			ext_save_config($config_file, $configuration);
        }   // end of enable extension
		else {
            exec("killall syncthing-inotify"); $savemsg = $savemsg." ".$configuration['appname'].gettext(" is now disabled!");
            $configuration['enable'] = isset($_POST['enable']);
			ext_save_config($config_file, $configuration);
        }   // end of disable extension
    }   // end of empty input_errors
}

$pconfig['enable'] = $configuration['enable'];
$pconfig['who'] = !empty($configuration['who']) ? $configuration['who'] : "";
$pconfig['if'] = !empty($configuration['if']) ? $configuration['if'] : "";
$pconfig['api_key'] = !empty($configuration['api_key']) ? $configuration['api_key'] : "";
$pconfig['syncthing_extension_path'] = !empty($configuration['syncthing_extension_path']) ? $configuration['syncthing_extension_path'] : "";
$pconfig['syncthing_ip'] = !empty($configuration['syncthing_ip']) ? $configuration['syncthing_ip'] : "";
$pconfig['syncthing_port'] = !empty($configuration['syncthing_port']) ? $configuration['syncthing_port'] : "";

// Use first interface as default if it is not set.
if (empty($pconfig['if']) && is_array($a_interface)) $pconfig['if'] = key($a_interface);

if (is_ajax()) {
	$procinfo = get_process_info();
    $syncthinginfo = get_process_info('syncthing');
	render_ajax(array('procinfo' => $procinfo, 'syncthinginfo' => $syncthinginfo));
}

if (($message = ext_check_version("{$configuration['rootfolder']}version_server.txt", "syncthing-inotify", $configuration['version'], gettext("Extension Maintenance"))) !== false) $savemsg .= $message;

bindtextdomain("nas4free", "/usr/local/share/locale");
include("fbegin.inc");?>
<script type="text/javascript">//<![CDATA[
$(document).ready(function(){
	var gui = new GUI;
	gui.recall(0, 2000, 'syncthing-inotify.php', null, function(data) {
		$('#procinfo').html(data.procinfo);
        $('#syncthinginfo').html(data.syncthinginfo);
	});
});
//]]>
</script>
<script type="text/javascript">
<!--
function enable_change(enable_change) {
	var endis = !(document.iform.enable.checked || enable_change);
	document.iform.xif.disabled = endis;
	document.iform.autoUpgradeIntervalH.disabled = endis;
	document.iform.resetuser.disabled = endis;
	document.iform.who.disabled = endis;
	document.iform.gui_enabled.disabled = endis;
}

function as_change() {
	switch(document.iform.as_enable.checked) {
		case false:
			showElementById('who_tr','hide');
			showElementById('xif_tr','hide');
			showElementById('gui_enabled_tr','hide');
            showElementById('syncthing_extension_path_tr','hide');
			showElementById('api_key_tr','hide');
            showElementById('syncthing_ip_tr','hide');
            showElementById('syncthing_port_tr','hide');
			break;

		case true:
			showElementById('who_tr','show');
			showElementById('xif_tr','show');
			showElementById('gui_enabled_tr','show');
            showElementById('syncthing_extension_path_tr','show');
			showElementById('api_key_tr','show');
            showElementById('syncthing_ip_tr','show');
            showElementById('syncthing_port_tr','show');
			break;
	}
}
//-->
</script>
<!-- The Spinner Elements -->
<?php include("ext/syncthing-inotify/spinner.inc");?>
<script src="ext/syncthing-inotify/spin.min.js"></script>
<!-- use: onsubmit="spinner()" within the form tag -->

<form action="syncthing-inotify.php" method="post" name="iform" id="iform" onsubmit="spinner()">
<?php bindtextdomain("nas4free", "/usr/local/share/locale-stg"); ?>
    <table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr><td class="tabnavtbl">
		<ul id="tabnav">
			<li class="tabact"><a href="syncthing-inotify.php"><span><?=gettext("Configuration");?></span></a></li>
			<li class="tabinact"><a href="syncthing_inotify_update.php"><span><?=gettext("Maintenance");?></span></a></li>
			<li class="tabinact"><a href="syncthing_inotify_update_extension.php"><span><?=gettext("Extension Maintenance");?></span></a></li>
			<li class="tabinact"><a href="syncthing_inotify_log.php"><span><?=gettext("Log");?></span></a></li>
		</ul>
	</td></tr>
    <tr><td class="tabcont">
        <?php if (!empty($input_errors)) print_input_errors($input_errors);?>
        <?php if (!empty($savemsg)) print_info_box($savemsg);?>
        <table width="100%" border="0" cellpadding="6" cellspacing="0">
			<?php html_titleline($configuration['appname']." ".gettext("Information"));?>
            <?php html_text("installation_directory", gettext("Installation directory"), sprintf(gettext("The extension is installed in %s."), $configuration['rootfolder']));?>
			<?php html_text("version", gettext("Version"), $configuration['product_version']);?>
			<?php html_text("architecture", gettext("Architecture"), $configuration['architecture']);?>
            <tr>
                <td class="vncell"><?=gettext("Inotify status");?></td>
                <td class="vtable"><span name="procinfo" id="procinfo"></span></td>
            </tr>
            <tr>
                <td class="vncell"><?=gettext("SyncThing Status");?></td>
                <td class="vtable"><span name="syncthinginfo" id="syncthinginfo"></span></td>
            </tr>
			<?php html_separator();?>
        	<?php html_titleline_checkbox("enable", $configuration['appname'], $pconfig['enable'], gettext("Enable"), "enable_change(false)");?>
    		<?php $a_user = array(); foreach (system_get_user_list() as $userk => $userv) { $a_user[$userk] = htmlspecialchars($userk); }?>
            <?php html_combobox("who", gettext("Username"), $pconfig['who'], $a_user, gettext("Specifies the username which the service will run as."), false);?>
            <?php html_filechooser("syncthing_extension_path", gettext("Synchthing Config"), $pconfig['syncthing_extension_path'], $g['media_path'], false, 60);?>
            <?php html_inputbox("api_key", gettext("Api key"), $pconfig['api_key'], gettext("Syncthing API key."), false, 60);?>
            <?php html_inputbox("syncthing_ip", gettext("SyncThing IP"), $pconfig['syncthing_ip'], gettext("Syncthing IP Address."), false, 60);?>
            <?php html_inputbox("syncthing_port", gettext("SyncThing Port"), $pconfig['syncthing_port'], gettext("Syncthing Port."), false, 60);?>
        </table>
        <div id="submit">
			<input id="save" name="save" type="submit" class="formbtn" value="<?=gettext("Save and Restart");?>"/>
        </div>
	</td></tr>
	</table>
	<?php include("formend.inc");?>
</form>
<script type="text/javascript">
<!--
enable_change(false);
as_change();
//-->
</script>
<?php include("fend.inc");?>
