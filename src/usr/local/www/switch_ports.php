<?php
/*
 * switch_ports.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

##|+PRIV
##|*IDENT=page-switch-ports
##|*NAME=Switch: Ports
##|*DESCR=Allow access to the 'Switch: Ports' page.
##|*MATCH=switch_ports.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("switch.inc");

if ($_REQUEST['ajax'] === "ajax") {
	if ($_REQUEST['vids']) {
		// Ensure there is some sort of switch configuration to work with
		if ($config['switches']['switch']) {
			$a_swports = &$config['switches']['switch'][0];
			unset($a_swports['swports']
				);
			// Decode the JSON array
			$ja = json_decode($_REQUEST['vids'], true);

			// Extract the port and VID from each item in the list
			$idx = 0;
			foreach ($ja['vids'] as $vid ) {
				$port = $vid['port'];
				$pvid = $vid['vid'];

				if (! vlan_valid_tag($pvid) ) {
					$input_errors[] = sprintf(gettext("%d is not a valid VID for port %s"), $pvid, $port);
				} else {
					$swporto[] = array('port' => htmlspecialchars($port), 'pvid' => htmlspecialchars($pvid);
					$a_swports['swports']['swport'][] = $swporto[$idx];
				}

				$idx++;
			}

			if (! $input_errors) {
				write_config("Updaing switch PVIDs");
				$savemsg = gettext("Port VIDs updated.");
			}
		} else {
			$input_errors[] = sprintf(gettext("THere is no switch configuration to modify!"));
		}
	}
}

$pgtitle = array(gettext("Interfaces"), gettext("Switch"), gettext("Ports"));
$shortcut_section = "ports";
include("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("System"), false, "switch_system.php");
$tab_array[] = array(gettext("Ports"), true, "switch_ports.php");
$tab_array[] = array(gettext("VLANs"), false, "switch_vlans.php");
display_top_tabs($tab_array);

// Build an array with which to populate the switch device selector
function get_switches($devicelist) {

	$switches = array();

	foreach ($devicelist as $swdev) {

		$swinfo = pfSense_etherswitch_getinfo($swdev);
		if ($swinfo == NULL) {
			continue;
		}
		if ($swdevice == NULL)
			$swdevice = $swdev;

		$switches[$swdev] = $swinfo['name'];
	}

	return($switches);
}

// List the available switches
$swdevices = switch_get_devices();
$swtitle = switch_get_title();

// If there is more than one switch, draw a selector to allow the user to choose which one to look at
if (count($swdevices) > 1) {
	$form = new Form(false);

	$section = new Form_Section('Dynamic DNS Client');

	$section->addInput(new Form_Select(
		'swdevice',
		'Switch',
		$_POST['swdevice'],
		get_switches($swdevices)
	));

	$form->add($section);

	print($form);

}

// If the selector was changed, the selected value becomes the default
if($_POST['swdevice']) {
	$swdevice = $_POST['swdevice'];
} else {
	$swdevice = $swdevices[0];
}

$swinfo = pfSense_etherswitch_getinfo($swdevice);
if ($swinfo == NULL) {
	$input_errors[] = "Cannot get switch device information\n";
}

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?= gettext($swtitle) ." ". gettext('Switch Ports')?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table id="vlanporttable" class="table table-striped table-hover table-condensed table-rowdblclickedit">
				<thead>
					<tr style="cursor:default;">
						<th><?=gettext("Port #"); ?></th>
						<th><?=gettext("Port name"); ?></th>
<?
	if (isset($swinfo['vlan_mode']) && $swinfo['vlan_mode'] == "DOT1Q") {
?>
						<th><?=gettext("Port VID"); ?></th>
<?
	}
?>
						<th><?=gettext("Flags"); ?></th>
						<th><?=gettext("Media"); ?></th>
						<th><?=gettext("Status"); ?></th>
					</tr>
				</thead>
				<tbody id="vlanporttablebody">
<?php
if (! $input_errors) {
	for ($i = 0; $i < $swinfo['nports']; $i++) {
		if (!switch_port_is_enabled($swinfo, $i)) {
			continue;
		}
		$port = pfSense_etherswitch_getport($swdevice, $i);
		if ($port == NULL) {
			continue;
		}
?>
					<tr style="cursor:default;">
						<td>
<?
		print(htmlspecialchars($port['port']));

?>
						</td>
						<td>
<?php
		$swport = switch_map_port($port['port']);
		if ($swport != NULL) {
			echo "$swport";
		} else {
			print(htmlspecialchars($port['port']));
		}
?>
						</td>
<?
		if ($swinfo['vlan_mode'] == "DOT1Q") {
?>
						<td title="<?=gettext("Click to edit")?>" class="editable icon-pointer">
							<?= htmlspecialchars($port['pvid'])?>
						</td>
<?
		}
?>
						<td>
<?
		$comma = false;
		foreach ($port['flags'] as $flag => $val) {
			if ($comma)
				echo ",";
			echo "$flag";
			$comma = true;
		}
?>
						</td>
						<td>
<?
		if (isset($port['media']['current'])) {
			echo htmlspecialchars($port['media']['current']);
			if (isset($port['media']['active'])) {
				echo " (". htmlspecialchars($port['media']['active']) .")";
			}
		}

		print('</td>');

		switch (strtolower(htmlspecialchars($port['status']))) {
			case 'no carrier':
				print('<td class="text-danger">');
				break;
			case 'active':
				print('<td class="text-success">');
				break;
			default:
				print('<td>');
				break;
		}

		print(ucwords(htmlspecialchars($port['status'])));
?>
						</td>
					</tr>
<?
		}
	}
?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<nav class="action-buttons">
<?php
	if ($swinfo['vlan_mode'] == "DOT1Q") { ?>
	<span class="pull-left text-info"><?=gettext("Click a Port VID to edit")?></span>
<?php } ?>
	<button name="submit" id="submit" type="submit" class="btn btn-primary btn-sm" value="<?= gettext("Save"); ?>" >
		<i class="fa fa-save icon-embed-btn"></i>
		<?=gettext("Save")?>
	</button>
</nav>

<div class="infoblock <?=$swinfo['vlan_mode'] == "DOT1Q" ? "":"blockopen" ?>">
<?php
	print_info_box(sprintf(gettext('%1$sVLAN IDs are displayed only if 802.1q VLAN mode is enabled on the "VLANs" tab. %2$s' .
		'The Port VIDs may be edited by clicking on the cell in the table above, then clicking "Save"'), '<b>', '</b><br />'), 'info', false);
?>
</div>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	$('#vlanporttable').editableTableWidget();	// Make the table cells editable (but only on <td>s with class editable)

	// Create a JSON array of VIDs by port, add it to a form and transmit
	$('#submit').on('click', function () {
		var form = $('<form></form>').attr("method", 'post');
		var json = '{"vids":[';
		var entry = 0;

		$('#vlanporttablebody tr').each(function() {
			if (entry > 0) {
				json += ",\n";
			}

			json = json + '{"port":"' + $(this).find('td:eq(0)').text().trim() + '", "vid":"' + $(this).find('td:eq(2)').text().trim() + '"}';
			entry++;
		});

		json += ']}';

		// Compose a form containing the PVIDs and submit it
		$('<input>').attr({
				type: 'hidden',
				name: 'vids',
				value: json
			}).appendTo(form);

		$('<input>').attr({
				type: 'hidden',
				name: 'ajax',
				value: 'ajax'
			}).appendTo(form);

		$('<input>').attr({
				type: 'hidden',
				name: '__csrf_magic',
				value: csrfMagicToken
			}).appendTo(form);

		$('body').append(form);
		$('form').submit();
	});
});
//]]>
</script>

<?php
include("foot.inc");