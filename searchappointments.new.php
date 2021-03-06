<?php

/**
 * api/searchappointments.new.php Search appointments.
 *
 * API is allowed to get list of appointments for search appointment .
 *
 * Copyright (C) 2012 Karl Englund <karl@mastermobileproducts.com>
 *
 * LICENSE: This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 3
 * of the License, or (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://opensource.org/licenses/gpl-3.0.html>;.
 *
 * @package OpenEMR
 * @author  Karl Englund <karl@mastermobileproducts.com>
 * @link    http://www.open-emr.org
 */
header("Content-Type:text/xml");
$ignoreAuth = true;

require_once('classes.php');


$appointment_type = $_POST['appointmentType']; // 1: All 2:person specefic
$token = $_POST['token'];
$from_date = !empty($_POST['fromDate']) ? $_POST['fromDate'] : '1900-00-00';
$to_date = !empty($_POST['endDate']) ? $_POST['endDate'] : date('Y-m-d');
$time = !empty($_POST['time']) ? $_POST['time'] : '';
$facilities = $_POST['facilities'];
$appstatus = stripslashes($_POST['status']);

$xml_string = "";
$xml_string .= "<Appointments>\n";


if ($userId = validateToken($token)) {
    $username = getUsername($userId);

    $acl_allow = acl_check('patients', 'appt', $username);
    if ($acl_allow) {
        $where = null;
        $provider_id = $userId;
        if ($facilities) {
            $where = " AND pc_facility IN ($facilities)";
        }
        if ($appstatus) {
            $where .= ' AND pc_apptstatus IN (' . $appstatus . ')';
        }
        if ($appointment_type == 2) {
            $where .= " AND pc_aid = {$provider_id}";
        }
        if ($time) {
            $where .= " AND pc_startTime = '{$time}'";
        }

        $events = fetchEvents($from_date, $to_date, $where, $orderby_param = null);


        if ($events) {
            $xml_string .= "<status>0</status>\n";
            $xml_string .= "<reason>Success processing patient appointments records</reason>\n";
            $counter = 0;

            foreach ($events as $event) {
                $xml_string .= "<Appointment>\n";

                foreach ($event as $fieldname => $fieldvalue) {
                    $rowvalue = xmlsafestring($fieldvalue);
                    $xml_string .= "<$fieldname>$rowvalue</$fieldname>\n";
                }

                $strQuery = 'SELECT pc_apptstatus,p.sex as gender,p.pid as p_id, pce.pc_facility,pce.pc_billing_location,f1.name as facility_name,f2.name as billing_location_name FROM openemr_postcalendar_events as pce
                                            LEFT JOIN `facility` as f1 ON pce.pc_facility = f1.id
                                            LEFT JOIN `facility` as f2 ON pce.pc_billing_location = f2.id
                                            LEFT JOIN patient_data AS p ON p.pid = pce.pc_pid 
                                        WHERE pc_eid = ?';
                
                $result = sqlQuery($strQuery, array($event['pc_eid']));
             
                $status = xmlsafestring($result['pc_apptstatus']);
                $xml_string .= "<gender>{$result['gender']}</gender>\n";
                $xml_string .= "<pc_apptstatus>{$status}</pc_apptstatus>\n";
                $xml_string .= "<pc_facility>{$result['pc_facility']}</pc_facility>\n";
                $xml_string .= "<facility_name>{$result['facility_name']}</facility_name>\n";
                $xml_string .= "<pc_billing_location>{$result['pc_billing_location']}</pc_billing_location>\n";
                $xml_string .= "<billing_location_name>{$result['billing_location_name']}</billing_location_name>\n";
                $patient_id = $result['p_id'];
                $strQuery2 = "SELECT d.url
                                FROM `documents` AS d
                                INNER JOIN `categories_to_documents` AS c2d ON d.id = c2d.document_id
                                WHERE d.foreign_id = ?
                                AND c2d.category_id = 13";

                 $result2 = sqlQuery($strQuery2, array($patient_id));
           
                if ($result2) {
                    $url = getUrl($result2['url']);
                    $xml_string .= "<patient_profile_image>{$url}</patient_profile_image>\n";
                } else {
                    $xml_string .= "<patient_profile_image></patient_profile_image>\n";
                }

                $xml_string .= "</Appointment>\n";
                $counter++;
            }
        } else {
            $xml_string .= "<status>-1</status>\n";
            $xml_string .= "<reason>Could not find results</reason>\n";
        }
    } else {
        $xml_string .= "<status>-2</status>\n";
        $xml_string .= "<reason>You are not Authorized to perform this action</reason>\n";
    }
} else {
    $xml_string .= "<status>-2</status>\n";
    $xml_string .= "<reason>Invalid Token</reason>\n";
}


$xml_string .= "</Appointments>\n";
echo $xml_string;
?>