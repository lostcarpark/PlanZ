<?php
// Copyright (c) 2020-2021 Peter Olszowka. All rights reserved. See copyright document for more details.
global $returnAjaxErrors, $return500errors;
$returnAjaxErrors = true;
$return500errors = true;
require_once('StaffCommonCode.php'); // will check if logged in and for staff privileges

$schema_loaded = false;
$schema = array();
$displayorder_found = false;
$prikey = '';
$json_return = array();

/**
 * Return maximum length of column contents.
 * Used in calculating more accurate table editor column widths.
 */
function fetch_max_length(string $tablename, $columnName): int
{
    $query = "SELECT MAX(LENGTH($columnName)) FROM $tablename;";
    $result = mysqli_query_exit_on_error($query);
    $row = $result->fetch_row();
    return $row[0] ?? 0;
}

function fetch_schema($tablename) {
    global $schema, $displayorder_found, $prikey, $schema_loaded;

    if ($schema_loaded == false) {
        $db = DBDB;
        //error_log("table = " . $tablename);
        // json of schema and table contents
        $query=<<<EOD
    SELECT
        COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, COLUMN_KEY, COLUMN_DEFAULT, IS_NULLABLE, EXTRA
    FROM
        INFORMATION_SCHEMA.COLUMNS
    WHERE
            TABLE_SCHEMA = '$db'
        AND TABLE_NAME = '$tablename'
    ORDER BY
        ORDINAL_POSITION;
EOD;
        $result = mysqli_query_exit_on_error($query);
        $schema = array();
        $displayorder_found = false;
        $prikey = '';
        while ($row = $result->fetch_assoc()) {
            // Add actual width of contents to results.
            $row['ACTUAL_LENGTH'] = fetch_max_length($tablename, $row['COLUMN_NAME']);
            $schema[] = $row;
            if ($row["COLUMN_NAME"] == 'display_order') {
                $displayorder_found = true;
            }
            if ($row["COLUMN_KEY"] == 'PRI') {
                $prikey = $prikey . $row["COLUMN_NAME"] . ",";
            }
        }

        $prikey = substr($prikey, 0, -1);

        mysqli_free_result($result);
        $schema_loaded = true;
    }
}

function update_table($tablename)
{
    global $json_return, $linki, $message_error, $schema, $displayorder_found, $prikey, $schema_loaded;

    if (!(may_I('ce_All') || may_I("ce_$tablename"))) {
        $message_error = "You do not have permission to view this page.";
        RenderErrorAjax($message_error);
        exit();
    }

    $rows = json_decode(base64_decode(getString("tabledata")));
    // $json_return['debug'] = print_r($rows, true);
    $tablename = getString("tablename");

    $indexcol = getString("indexcol");

    fetch_schema($tablename);
    // reset display order to match new order and find which rows to delete
    $idsFound = "";
    $display_order = 10;
    foreach ($rows as $row) {
        if ($row->display_order >= 0) {
            $row->display_order = $display_order;
            $display_order = $display_order + 10;
        }
        $id = (int)$row->$indexcol;
        if ($id) {
            $idsFound = $idsFound . ',' . $id;
        }
    }
    //error_log($idsFound);

    // delete the ones no longer in the JSON uploaded, check for none uploaded
    if (mb_strlen($idsFound) < 2) {
        $sql = "DELETE FROM $tablename WHERE $indexcol >= 0;";
    }
    else {
        $sql = "DELETE FROM $tablename WHERE $indexcol NOT IN (" . mb_substr($idsFound, 1) . ");";
    }
    //error_log("\ndelete unused rows = '" . $sql . "'");

    if (!mysqli_query_exit_on_error($sql)) {
        exit(); // Should have exited already.
    }
    $deleted = mysqli_affected_rows($linki);

    // insert new rows (those with id < 0)
    $inserted = 0;
    $fieldcount = 0;
    $datatype = "";
    $sql = "INSERT INTO $tablename (";
    foreach($schema as $col) {
        //var_error_log($col);
        if ($col['EXTRA'] != 'auto_increment') {
            $sql .= $col['COLUMN_NAME'] . ',';
            $datatype .= strpos($col['DATA_TYPE'], 'int') !== false ? 'i' : 's';
            $fieldcount++;
        }
    }
    if ($fieldcount > 0) {
        $sql = substr($sql, 0, -1) . ") VALUES (";
        for ($i = 0; $i < $fieldcount; $i++) {
            $sql .= "?,";
        }
        $sql = substr($sql, 0, -1) . ");";

        foreach ($rows as $row) {
            $paramarray = array();
            $id = (int)$row->$indexcol;
            if ($id < 0) {
                foreach($schema as $col) {
                    if ($col['EXTRA'] != 'auto_increment') {
                        $name = $col['COLUMN_NAME'];
                        // If uploaded data has column property, and it's not an empty string, add to paramarray. Otherwise add a null (null is needed to prevent error inserting non-char fields).
                        $paramarray[] = (property_exists($row, $name) && trim($row->$name) !== '' ? trim($row->$name) : null);
                    }
                }

                //error_log("\n\nInsert of '$id' with datatype of '$datatype'");
                //error_log($sql);
                //var_error_log($paramarray);
                $inserted += mysql_cmd_with_prepare($sql, $datatype, $paramarray);
            }
        }
    }

    // update existing rows (those with id >= 0)
    $updated = 0;
    $datatype = "";

    $sql = "UPDATE $tablename SET\n";
    $keytype = 's';
    foreach($schema as $col) {
        if ($col['COLUMN_KEY'] != 'PRI') {
            if ($col['COLUMN_NAME'] != 'Usage_COUNT') {
                $sql .= "\t" . $col['COLUMN_NAME'] . " = ?,\n";
                $datatype .= strpos($col['DATA_TYPE'], 'int') !== false ? 'i' : 's';
                $fieldcount++;
            }
        }
        else {
            $keytype = strpos($col['DATA_TYPE'], 'int') !== false ? 'i' : 's';
        }
    }
    $sql = substr($sql, 0, -2) .  "\nWHERE $prikey = ?;";
    $datatype .= $keytype;;
    //error_log($sql);
    //error_log($datatype);
    foreach ($rows as $row) {
        $id = $row->$prikey;
        //error_log("\n\nUpdate Loop: " . $id);
        if ($id >= 0) {
            $paramarray = array();
            foreach ($schema as $col) {
                if ($col['COLUMN_KEY'] != 'PRI') {
                    $colname = $col['COLUMN_NAME'];
                    if ($colname != 'Usage_COUNT') {
                        $paramarray[] = $row->$colname;
                    }
                }
            }
            $paramarray[] = $id;
            //error_log("\n\nupdate of '$id' with '$datatype'\n" . $sql);
            //var_error_log($paramarray);
            $updated += mysql_cmd_with_prepare($sql, $datatype, $paramarray);
        }
    }

    $message = "";
    if ($deleted > 0) {
        $message = ", " . $deleted . " rows deleted";
    }
    if ($inserted > 0) {
        $message = $message . ", " . $inserted . " rows inserted";
    }
    if ($updated > 0) {
        $message = $message . ", " . $updated . " rows updated";
    }

    if (mb_strlen($message) > 2) {
        $message = "<p>Database changes: " . mb_substr($message, 2) . "</p>";
    }
    else {
        $message = "";
    }

    // get updated survey now with the id's in it
    fetch_table($tablename, $message);
}

/*
 *  Return the name of the first column ending with "name" on the table.
 */
function lookupNameColumn($tableName)
{
    $db = DBDB;
    $query = <<<EOD
        SELECT
            COLUMN_NAME
        FROM
            INFORMATION_SCHEMA.COLUMNS
        WHERE
            TABLE_SCHEMA = '$db'
            AND TABLE_NAME = '$tableName'
            AND COLUMN_NAME LIKE '%name';
EOD;
    $result = mysqli_query_exit_on_error($query);
    if ($row = $result->fetch_object()) {
      return $row->COLUMN_NAME;
    }

    return null;
}

/*
 *  Return the name of the first column of type "varchar" on the table.
 */
function lookupVarcharColumn($tableName)
{
    $db = DBDB;
    $query = <<<EOD
        SELECT
            COLUMN_NAME
        FROM
            INFORMATION_SCHEMA.COLUMNS
        WHERE
            TABLE_SCHEMA = '$db'
            AND TABLE_NAME = '$tableName'
            AND DATA_TYPE = 'varchar';
EOD;
    $result = mysqli_query_exit_on_error($query);
    if ($row = $result->fetch_object()) {
      return $row->COLUMN_NAME;
    }

    return null;
}

function fetch_table($tablename, $message) {
    global $schema, $displayorder_found, $json_return, $prikey;
    $db = DBDB;
    if (!(may_I('ce_All') || may_I("ce_$tablename"))) {
        $message_error = "You do not have permission to view this page.";
        RenderErrorAjax($message_error);
        exit();
    }

    if (strpos($tablename, ' ', 0) !== false) {
        $json_return["message"] = $tablename;
        echo json_encode($json_return) . "\n";
        return;
    }

    // json of schema and table contents
    fetch_schema($tablename);
    $json_return["tableschema"] = $schema;

    // get the foreign keys
    $query = <<<EOD
    SELECT
        TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
    FROM
        INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE
            TABLE_SCHEMA = '$db'
        AND (
               TABLE_NAME = '$tablename'
            OR REFERENCED_TABLE_NAME = '$tablename'
            )
        AND CONSTRAINT_NAME != 'PRIMARY'
        AND REFERENCED_COLUMN_NAME != ''
    ORDER BY
        COLUMN_NAME, TABLE_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME;
EOD;
    $foreign_keys = array();
    $referenced_columns = array();
    $result = mysqli_query_exit_on_error($query);
    while ($row = $result->fetch_assoc()) {
        if (strcasecmp($row["TABLE_NAME"], $tablename) == 0) {
            // table refers to another table for one of its fields;
            $referenced_columns[] = $row["COLUMN_NAME"] . ":" . $row["REFERENCED_TABLE_NAME"] . "." . $row["REFERENCED_COLUMN_NAME"];
        }
        else {
            // table is referenced by another table
            $foreign_keys[] = array(
                "TABLE_NAME" => $row["TABLE_NAME"],
                "COLUMN_NAME" => $row["COLUMN_NAME"],
                "REFERENCED_TABLE_NAME" => $row["REFERENCED_TABLE_NAME"],
                "REFERENCED_COLUMN_NAME" => $row["REFERENCED_COLUMN_NAME"]);
        }
    }

    $withclause = "";
    $joinclause = "";
    $curfield = "";
    $mycurname = "";
    $union = "";
    $occurs = "";

    // Build CTE's for getting count of foreign key usage
    if (count($foreign_keys) > 0 ) {
        foreach ($foreign_keys as $key) {
            $mycolname = $key["REFERENCED_COLUMN_NAME"];
            $reftable = $key["TABLE_NAME"];
            $reffield = $key["COLUMN_NAME"];
            if ($reffield != $curfield) {
                $union = "";
                if (DBVER >= "8") {
                    if ($withclause == "")
                        $withclause = "WITH Ref" . $reffield . " AS (\n";
                    else {
                        $withclause .= "), SUM$curfield AS (\nSELECT $curfield, SUM(occurs) AS occurs FROM Ref$curfield GROUP BY $curfield\n), Ref" . $reffield . " AS (\n";
                        $joinclause .= "LEFT OUTER JOIN SUM$curfield ON ($tablename.$mycurname = SUM$curfield.$curfield)\n";
                        if ($occurs != "")
                            $occurs .= "+";
                        $occurs .= "SUM$curfield.occurs";
                    }
                }
                else {
                    if ($joinclause == "")
                        $joinclause = "LEFT OUTER JOIN (\nSELECT $reffield, SUM(occurs) AS occurs FROM (";
                    else {
                        $joinclause .= ") Ref$curfield\nGROUP BY $curfield\n) SUM$curfield ON ($tablename.$mycurname = SUM$curfield.$curfield)\nLEFT OUTER JOIN (\nSELECT $reffield, SUM(occurs) AS occurs FROM (";
                        if ($occurs != "")
                            $occurs .= "+";
                        $occurs .= "SUM$curfield.occurs";
                    }
                }

                $mycurname = $mycolname;
                $curfield = $reffield;
            }
            if (DBVER >= "8")
                $withclause .= "$union SELECT '$reftable', $reffield, COUNT(*) AS occurs FROM $reftable\n";
            else
                $joinclause .= "$union SELECT '$reftable', $reffield, COUNT(*) AS occurs FROM $reftable GROUP BY $curfield\n";
            $union = "UNION ALL";
        }
        if (DBVER >= "8") {
            $withclause .= "), SUM$curfield AS (\nSELECT $curfield, SUM(occurs) AS occurs FROM Ref$curfield GROUP BY $curfield\n)\n";
            $joinclause .= "LEFT OUTER JOIN SUM$curfield ON ($tablename.$mycurname = SUM$curfield.$curfield)\n";
        }
        else {
            $joinclause .= ") Ref$curfield\nGROUP BY $curfield\n) SUM$curfield ON ($tablename.$mycurname = SUM$curfield.$curfield)\n";
        }
        if ($occurs != "")
            $occurs .= "+";
        $occurs .= "SUM$curfield.occurs";
        $occurs = "CASE WHEN $occurs IS NULL THEN 0 ELSE $occurs END AS Usage_Count";
    }
    else
        $occurs = "0 AS Usage_Count";

    // table select - get select list for field that is a foreign key to another table
    foreach($referenced_columns as $key) {
        $colonpos = strpos($key, ':');
        $colname = substr($key, 0, $colonpos);
        $periodpos = strpos($key, '.');
        $reftable = substr($key, $colonpos + 1, $periodpos - ($colonpos + 1));
        $reffield = substr($key, $periodpos + 1);

        $namefield = lookupNameColumn($reftable);
        if (is_null($namefield)) $namefield = lookupVarcharColumn($reftable);
        $data = array();
        $query = "SELECT $reffield AS id, $namefield AS name FROM $reftable ORDER BY display_order;";
        $result = mysqli_query_exit_on_error($query);
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        mysqli_free_result($result);
        if (count($data) == 0) {
            if ($message != "") {
                $message .= "<br/>";
            }
            $message .= "Warning: Cannot edit this table until the table $reftable has been edited and is not empty";
        }
        $json_return[$colname . "_select"] = $data;
    }

    // now get the data rows
    $query = "$withclause SELECT $occurs, $tablename.* FROM $tablename\n$joinclause";
    if ($displayorder_found)
        $query = $query . "ORDER BY display_order;";
    else if ($prikey != ",")
        $query = $query . "ORDER BY " . $prikey . ";";

    //error_log($query);
    $result = mysqli_query_exit_on_error($query);
    $rows = array();
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    mysqli_free_result($result);
    $json_return["tabledata"] = $rows;

    if ($message != "") {
        $json_return["message"] = $message;
    }
    echo json_encode($json_return) . "\n";
}

// Start here.  Should be AJAX requests only
$ajax_request_action = getString("ajax_request_action");
if ($ajax_request_action == "") {
    exit();
}

switch ($ajax_request_action) {
    case "fetchtable":
        $tablename = getString("tablename");
        fetch_table($tablename, "");
        break;
    case "updatetable":
        $tablename = getString("tablename");
        update_table($tablename);
        break;
    default:
        exit();
}
?>
