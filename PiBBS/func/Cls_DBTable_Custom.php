<?php
//
// A class for manipulating a database table.
//
// This class will read from database schema and automatically build the
// view/edit/verify forms.
//
// Customized from Cls_DBTable with this change:
// 1) Instead of providing a list of hidden fields, provide a list of given fields and titles.
// 2) Added styles for TB, TR, TD.
// 3) Added en/cn languages for buttons.
//
// This is convenient when you want to use custom field titles, and in different languages.
//
// @Author: Xin Chen
// @Created on: 7/26/2013
// @Last modified: 7/26/2013
//

require_once("../func/db.php");
require_once("../func/util.php");
include_once("../func/setting.php");

class Cls_DBTable_Custom {
  
    private $_DEBUG;
    private $tbl_name;
    private $tbl_pk;
    private $cols_pwd;
    private $cols_default;  // an array, for columns use default values (show, no update).
    private $fields;
    private $titles;
    private $fields_list;

    private $tb_style;
    private $th_style;
    private $tr_style1; // style of odd row.
    private $tr_style2; // style of even row.
    private $td1_style; // style of the first column of table (often used for field title).

    private $lang;
    private $txt_viewFormEdit;
    private $txt_editFormVerify;
    private $txt_editFormCancel;
    private $txt_veriFormSubmit;
    private $txt_veriFormEdit;
    private $txt_repeat;
    private $txt_pwd_no_change;
    private $txt_not_empty;

    function __construct() {

    }

    public function init($tbl_name, $tbl_pk, $cols_pwd = array(), 
                         $cols_default = array(), $fields, $titles) {

        $this->_DEBUG = 0; // if 1, will print out mysql_error(), which may be insecure.

        $this->tbl_name = $tbl_name;
        $this->tbl_pk   = $tbl_pk;
        $this->cols_pwd = $cols_pwd;
        $this->cols_default = $cols_default;
        $this->fields = $fields;
        $this->titles = $titles;
        $this->fields_list = $this->arrayToList($this->fields, ",");
        //print_r($this->cols_pwd);
        //print_r($this->cols_default);

        $this->setTBStyle("");
        $this->setTHStyle("");
        $this->setTRStyle("");
        $this->setTD1Style("");

        $this->setLang("en");
    }

    function __destruct() {
        // do nothing.
    }

    function setTBStyle($style) {
        $this->tb_style = $style;
    }

    function setTHStyle($style) {
        $this->th_style = $style;
    }

    function setTRStyle($style1, $style2="") {
        $this->tr_style1 = $style1;
        $this->tr_style2 = ($style2 == "" ? $style1 : $style2);
    }

    function setTD1Style($style) {
        $this->td1_style = $style;
    }

    function setLang($lang) {
        $this->lang = $lang;

        if ($this->lang == "cn") {
            $this->txt_viewFormEdit = "修改";
            $this->txt_editFormVerify = "验证";
            $this->txt_editFormCancel = "取消修改";
            $this->txt_veriFormSubmit = "提交";
            $this->txt_veriFormEdit = "继续修改";
            $this->txt_repeat = "（重复）";
            $this->txt_pwd_no_change = "（若无改变不填写）";
            $this->txt_not_empty = "不能为空";
        } else { // default is "en".
            $this->txt_viewFormEdit = "Edit Content";
            $this->txt_editFormVerify = "Verify";
            $this->txt_editFormCancel = "Cancel Edit";
            $this->txt_veriFormSubmit = "Final Submit";
            $this->txt_veriFormEdit = "Edit More";
            $this->txt_repeat = "(repeat)";
            $this->txt_pwd_no_change = "(leave blank if no change)";
            $this->txt_not_empty = "Cannot be empty";
        }
    }

    // Get type information from database schema.
    private function getTypeInfo($s, &$type, &$len) {
        $type = "";
        $len = "";

        if ($s == "datetime") {
            $type = "datetime";
            $len = "";
        }
        else if ( preg_match("/varchar\(([0-9]+)\)/", $s, $type_len) ) {
            $type = "varchar";
            $len = $type_len[1];
        }
        else if ( preg_match("/int\(([0-9]+)\)/", $s, $type_len) ) {
            $type = "int";
            $len = $type_len[1];
        }

        //print "[$s: type=$type, len=$len]<br>";
    }

    //
    // Output the form to edit an entry.
    // $xml - from getDBTableAsXml("SELECT * FROM $tbl WHERE ID = '$id'").
    // $change_default - 1 if allow change default value, 0 otherwise.
    //
    // If want to change value of default cols, set $change_default = 1 both here
    // and in function call to update().
    //
    public function writeEditForm($id, $change_default = 0, $back_url = '', $verifyFirst = 0, $pwd_needed = '1') {
        $query = "SELECT $this->fields_list FROM $this->tbl_name WHERE $this->tbl_pk = " 
                 . db_encode($id); //echo $query;
        $xml = $this->getDBTableAsXml($query);
        $record = new SimpleXMLElement($xml);
        $cols = $record->row[0];
        //echo "[[[" . $cols->first_name . "...]]]<br/>";
        $schema = $this->getDBTableSchema();

        $IsPostBack = (U_REQUEST('IsPostBack') != '');
        $s = "";
        $row_ct = 0;
        foreach ($schema->xpath('//row') as $field) {
            // Pkey is autoincrement. Don't add to the new form.
            $name = trim( $field->Field );
            //if ($name == $this->tbl_pk) continue;

            // Get type and length.
            $type = ""; $type_len = "";
            $this->getTypeInfo($field->Type, $type, $type_len);
            //print "[type: $type, len: $type_len] ";
            $max_len = "";
            if ($type == "int" || $type == "varchar") {
                if ($type_len != "") $max_len = " maxlength=\"$type_len\"";
            }

            //if (in_array($name, $this->cols_hidden)) continue;
            if (! in_array($name, $this->fields)) continue;

            $allow_null = ($field->Null != 'NO');
            $star = $allow_null ? "" : "<font color='red'>*</font>";

            ++ $row_ct;
            $style = ($row_ct & 1 ? $this->tr_style1 : $this->tr_style2);
            //$s .= "<tr><td>&nbsp;" . ucfirst($name) . "$star&nbsp;</td>";
            $s .= "<tr $style><td $this->td1_style>&nbsp;" . $this->titles["$name"] . "$star&nbsp;</td>";

            $readonly = ($name == $this->tbl_pk ||
                        ($change_default == 0 && in_array($name, $this->cols_default))) ?
                        " readonly " : "";
            $v = isset( $_REQUEST["txt_$name"] ) ? trim( $_REQUEST["txt_$name"] ) : $cols->$name;
            $v = db_htmlEncode($v);

            $note = '';
            if ( in_array($name, $this->cols_pwd) ) {
                $type = 'password';
                //$v = isset( $_REQUEST["txt_$name"] ) ? $_REQUEST["txt_$name"] : '';
                $note = " $this->txt_pwd_no_change";
                if ($IsPostBack) {
                    $errMsg = $this->verify_pwd($name, $pwd_needed);
                    if ($errMsg != "") {
                        $errMsg = writeP2(" $errMsg", 0);
                        $note = $errMsg;
                    }
                }
                $v = ""; // don't keep value of password fields.
            } else {
                $type = 'text';
                if (! $allow_null && $IsPostBack && $v == "") { $note = writeP2(" $this->txt_not_empty", 0); }
            }

            if ( $name == $this->tbl_pk || in_array($name, $this->cols_default) ) {
                $s .= "<td>$v<input type=\"hidden\" $readonly name=\"txt_$name\" width=\"50\" value=\"$v\"/>$note</td></tr>\n";
            } else {
                $s .= "<td><input type=\"$type\" $readonly name=\"txt_$name\" width=\"50\" value=\"$v\" $max_len/>$note</td></tr>\n";
            }

            if ( in_array($name, $this->cols_pwd) ) {
                //$s .= "<tr><td>&nbsp;" . ucfirst($name) . "$star $this->txt_repeat&nbsp;</td>";
                $s .= "<tr $style><td $this->td1_style>&nbsp;" . $this->titles["$name"] . "$star $this->txt_repeat&nbsp;</td>";
                $s .= "<td><input type=\"$type\" $readonly name=\"txt_2_$name\" width=\"50\" value=\"\" $max_len/></td></tr>";
            }
        }

        $s = "<table $this->tb_style>\n$s</table>\n<br/>\n";
        if ($verifyFirst) {
            $s .= "<input type=\"submit\" name=\"btnVerify\" value=\"$this->txt_editFormVerify\" />";
        } else {
            $s .= "<input type=\"submit\" name=\"btnSubmit\" value=\"Submit\" />";
        }
        //$s .= "<input type=\"reset\" value=\"Reset\" />";
        if ($back_url != "") {
            $s .= "<input type=\"button\" value=\"$this->txt_editFormCancel\" onclick=\"javascript: window.location.href='$back_url'\" />";
        }
        $s .= "<input type='hidden' name='IsPostBack' value='IsPostBack'>";

        return $s;
    }

    //
    // Given an array, return a string consists of array elements, separated by delimiter.
    //
    private function arrayToList($a, $delimiter) {
        $ct = count($a);
        if ($ct == 0) return "";

        $s = $a[0];
        for ($i = 1; $i < $ct; ++ $i) {
            $s .= "$delimiter $a[$i]";
        }
        return $s;
    }

    //
    // Show view instead of Edit form. Adapted from writeEditForm().
    //
    public function writeViewForm($id, $change_default = 0) {
        $query = "SELECT $this->fields_list FROM $this->tbl_name WHERE $this->tbl_pk = " . db_encode($id);
        //echo $query; print_r($titles);
        $xml = $this->getDBTableAsXml($query);
        $record = new SimpleXMLElement($xml);
        $cols = $record->row[0];
        $schema = $this->getDBTableSchema();

        $s = "";
        $row_ct = 0;
        foreach ($schema->xpath('//row') as $field) {
            // Pkey is autoincrement. Don't add to the new form.
            $name = $field->Field;
            //if ($name == $this->tbl_pk) continue;
            if (! in_array($name, $this->fields)) continue;

            //$allow_null = ($field->Null != 'NO');
            //$star = $allow_null ? "" : "<font color='red'>*</font>";
            $star = ""; // don't use star in view form.

            ++ $row_ct;
            $style = ($row_ct & 1 ? $this->tr_style1 : $this->tr_style2);
            //$s .= "<tr><td>&nbsp;" . ucfirst($name) . "$star&nbsp;</td>";
            $s .= "<tr $style><td $this->td1_style>&nbsp;" . $this->titles["$name"] . "$star&nbsp;</td>";

            $v = db_htmlEncode( $cols->$name );

            if ( in_array($name, $this->cols_pwd) ) {
                $v = "********"; 
            }

            $s .= "<td>&nbsp; $v &nbsp;</td>";
        }

        $s = "<table $this->tb_style>$s</table><br/><a href='?mode=edit'>$this->txt_viewFormEdit</a>";
        return $s;
    }


    private function getDBTableSchema() {
        //print $this->getDBTableAsHtmlTable("show columns from $this->tbl_name", "$this->tbl_name Columns", 0);
        $query = "show columns from $this->tbl_name";
        $xml = $this->getDBTableAsXml($query);
        return new SimpleXMLElement($xml);
    }

    // Output the form to add new entry.
    // @params:
    //  - star_loc: location of star for non-empty fields. 
    //              1 - after field title, 2 - after textbox.
    public function writeNewForm($change_default = 0, $back_url = '', $useCaptcha = 0, $useVerify = 1, $star_loc = 1) {
        $schema = $this->getDBTableSchema();
        //print_r($record);
        $IsPostBack = (U_REQUEST('IsPostBack') != '');
        $s = "";
        foreach ($schema->xpath('//row') as $field) {
            // Pkey is autoincrement. Don't add to the new form.
            $name = $field->Field;

            //if (in_array($name, $this->cols_hidden)) continue;
            if (! in_array($name, $this->fields)) continue;

            $default = $field->Default;
            $unique  = ($field->Key == 'UNI');
            $allow_null = ($field->Null != 'NO');
            $star = $allow_null ? "" : "<font color='red'>*</font>";
            if ($star_loc == 1) { $star1 = $star; $star2 = ""; }
            else if ($star_loc == 2) { $star1 = ""; $star2 = $star; }

            if ($name== $this->tbl_pk ||
                ($change_default == 0 && in_array($name, $this->cols_default))) continue;
            //$s .= "<tr><td>&nbsp;" . ucfirst($name) . ": $star1&nbsp;</td>";
            $s .= "<tr><td>&nbsp;" . $this->title["$name"] . ":$star1&nbsp;</td>";
            $v = U_REQUEST("txt_$name") ? U_REQUEST("txt_$name") : $default;
            $v = db_htmlEncode($v);

            $errMsg = "";
            if ( in_array($name, $this->cols_pwd) ) {
                $type = "password";
                if ($IsPostBack) { 
                    $errMsg = $this->verify_pwd($name);
                    if ($errMsg != "") $errMsg = writeP2(" $errMsg", 0);
                }
                $v = ""; // don't keep posted back value for password field.
            } else {
                $type = "text";
                if (! $allow_null && $IsPostBack && $v == "") { $errMsg = writeP2(" $this->txt_not_empty", 0); }
            }

            $s .= "<td><input type=\"$type\" name=\"txt_$name\" width=\"50\" value=\"$v\">$star2 $errMsg</td></tr>\n";

            if ( in_array($name, $this->cols_pwd) ) {
                $s .= "<tr><td>&nbsp;" . ucfirst($name) . ":$star1 $this->txt_repeat&nbsp;</td>";
                $s .= "<td><input type=\"$type\" name=\"txt_2_$name\" width=\"50\" value=\"$v\">$star2</td></tr>\n";
            }
        }

        if ($useCaptcha) {
            $errMsg = "";
            if (! $allow_null && $IsPostBack && $v == "") { $errMsg = writeP2(" Should match image code", 0); }
            $t = file_get_contents("../func/captcha2.inc");
            $t = str_replace("#errMsg", $errMsg, $t);
            $s .= $t;
        }

        $s = "<table>$s</table><br/>";
        $btnName = $useVerify ? "btnVerify" : "btnSubmit";
        $s .= "<input type=\"submit\" name=\"$btnName\" value=\"Submit\">";
        $s .= "<input type=\"reset\" value=\"Reset\">";
        //$s .= "<input type='submit' name='btnClear' value='Clear Form'>";
        if ($back_url != "") {
            $s .= "<input type=\"button\" value=\"Cancel Add New\" onclick=\"javascript: window.location.href='$back_url'\" />";
        }
        $s .= "<input type='hidden' name='IsPostBack' value='IsPostBack'>";
        return $s;
    }


    public function writeVerifyForm($change_default = 0, $back_url = '') {
        $schema = $this->getDBTableSchema();
        //print_r($record);
        $s = "";
        $row_ct = 0;
        foreach ($schema->xpath('//row') as $field) {
            // Pkey is autoincrement. Don't add to the new form.
            $name = $field->Field;

            //if (in_array($name, $this->cols_hidden)) continue;
            if (! in_array($name, $this->fields)) continue;

            $default = $field->Default;
            $unique  = ($field->Key == 'UNI');
            $allow_null = ($field->Null != 'NO');
            $star = $allow_null ? "" : "<font color='red'>*</font>";

            //if ($name== $this->tbl_pk ||
            //    ($change_default == 0 && in_array($name, $this->cols_default))) continue;
            if (($change_default == 0 && in_array($name, $this->cols_default))) continue;

            ++ $row_ct;
            $style = ($row_ct & 1 ? $this->tr_style1 : $this->tr_style2);
            //$s .= "<tr><td>&nbsp;" . ucfirst($name) . "$star&nbsp;</td>";
            $s .= "<tr $style><td $this->td1_style>&nbsp;" . $this->titles["$name"] . "$star&nbsp;</td>";
            $v = isset( $_REQUEST["txt_$name"] ) ? trim( $_REQUEST["txt_$name"] ) : $default;
            $v = db_htmlEncode($v);
            $h = "<input type=\"hidden\" name=\"txt_$name\" value=\"$v\" />";

            if ( in_array($name, $this->cols_pwd) ) {
                $s .= "<td>&nbsp;********&nbsp;$h</td></tr>\n";

                $v2 = isset( $_REQUEST["txt_2_$name"] ) ? trim( $_REQUEST["txt_2_$name"] ) : "";
                $v2 = db_htmlEncode($v);
                $h = "<input type=\"hidden\" name=\"txt_2_$name\" value=\"$v2\" />";
                //$s .= "<tr><td>&nbsp;" . ucfirst($name) . "$star $this->txt_repeat&nbsp;</td>";
                $s .= "<tr $style><td $this->td1_style>&nbsp;" . $this->titles["$name"] . "$star $this->txt_repeat&nbsp;</td>";
                $s .= "<td>&nbsp;********&nbsp;$h</td></tr>\n";
            }
            else {
                $s .= "<td>&nbsp;$v&nbsp;$h</td></tr>\n";
            }
        }
        $s = "<table $this->tb_style>$s</table><br/>";
        $s .= "<input type=\"submit\" name=\"btnSubmit\" value=\"$this->txt_veriFormSubmit\">";
        if ($back_url != "") {
            $s .= "<input type=\"submit\" value=\"$this->txt_veriFormEdit\" />";
        }

        //$s .= writeP("Please click on \"Submit\" button to finish the change", 0);
        return $s;
    }


    // Check how many times a field value exists in this table.
    public function getRowCount($field, $value) {
        $query = "SELECT $field FROM $this->tbl_name WHERE $field = " . db_encode($value);
        //echo "$query<br>";
        return executeRowCount($query);
    }

    // Check if a field value already exists in this table.
    public function checkExist($field, $value) {
        return $this->getRowCount($field, $value) > 0;
    }

    //
    // if $pwd_needed is 0, don't verify it if both fields are empty.
    //
    public function verifyForm($change_default = 0, $useCaptcha = '0', $pwd_needed = '1') {
        $schema = $this->getDBTableSchema();
        $s = "";
        foreach ($schema->xpath('//row') as $field) {
            // Pkey is autoincrement. Don't add to the new form.
            $name = $field->Field;

            //if (in_array($name, $this->cols_hidden)) continue;
            if (! in_array($name, $this->fields)) continue;

            $default = $field->Default;
            $unique  = ($field->Key == 'UNI');
            $allow_null = ($field->Null != 'NO');

            if ($name== $this->tbl_pk ||
                ($change_default == 0 && in_array($name, $this->cols_default))) continue;

            $v = isset( $_REQUEST["txt_$name"] ) ? trim( $_REQUEST["txt_$name"] ) : $default;

            if ($unique) { 
                //echo "unique: " . $this->checkExist($name, $v);
            }

            if ( in_array($name, $this->cols_pwd) ) {
                $v2 = isset( $_REQUEST["txt_2_$name"] ) ? trim( $_REQUEST["txt_2_$name"] ) : "";

                if ($v == '' && $v2 == '') { // if new password is empty, do not insert.
                    if ($pwd_needed) {
                        $s .= "Password cannot be empty.\n";
                    }
                }
                else if ($v != $v2) {
                    $s .= "The two entries for $name should match.\n";
                }
                else {
                    $e = $this->validate_password_rule($v);
                    if ($e != "") { $s .= "$e\n"; }
                }
            }
            else {
                if (! $allow_null && $v == '') {
                    $s .= "Field $name cannot be empty\n";
                }
            }
        }

        if ($useCaptcha) {
            //echo $_SESSION['captcha'] . " ?= " . $_POST['txtCaptcha'] . "<br/>";
            if ($_SESSION['captcha'] != $_POST['txtCaptcha']) {
                $s .= "The image code entered is not correct.\n";
            }
        }

        if ($s != '') {
            $s = str_replace("\n", "<br/>", $s);
            $s = "<font color='red'>Error:<br/>$s</font>";
        }

        return $s;
    }

    // $name: name of the column field.
    private function verify_pwd($name, $pwd_needed = '1') {
        $s = "";
        $v  = isset( $_REQUEST["txt_$name"] ) ? trim( $_REQUEST["txt_$name"] ) : "";
        $v2 = isset( $_REQUEST["txt_2_$name"] ) ? trim( $_REQUEST["txt_2_$name"] ) : "";
        if ($v == '' && $v2 == '') { // if new password is empty, do not insert.
            if ($pwd_needed) {
                $s = $this->txt_not_empty . "\n";
            }
        }
        else if ($v != $v2) {
            //$s = "Two password entries should match\n";
            $s = validate_password_rule($v, $v2, 1) . "\n";
        }
        else {
            $e = $this->validate_password_rule($v);
            if ($e != "") { $s = "$e\n"; }
        }
        return $s;
    }

    // $v1, $v2: value of the 2 password entries.
    private function verify_pwd_2($v, $v2) {
        $s = "";
        //$v2 = isset( $_REQUEST["txt_2_$name"] ) ? trim( $_REQUEST["txt_2_$name"] ) : "";
        if ($v == '' && $v2 == '') { // if new password is empty, do not insert.
            $s = "Password cannot be empty.\n";
        }
        else if ($v != $v2) {
            $s = "The two entries for $name should match.\n";
        }
        else {
            $e = $this->validate_password_rule($v);
            if ($e != "") { $s = "$e\n"; }
        }
        return $s;
    }

    public function writeNewForm2() {
        $cols = $this->getDBTableColumnsAsArray($this->tbl_name);
        $ct = count($cols);
        $s = "";
        for ($i = 0; $i < $ct; ++ $i) {
            // Pkey is autoincrement. Don't add to the new form.
            if ($cols[$i] == $this->tbl_pk ||
                in_array($name, $this->cols_hidden) ||
                in_array($cols[$i], $this->cols_default)) continue;
            $s .= "<tr><td>&nbsp;$cols[$i]&nbsp;</td>";
            $v = trim( $_REQUEST["txt_$cols[$i]"] );

            $type = in_array($cols[$i], $this->cols_pwd) ? 'password' : 'text';
            $s .= "<td><input type='$type' name='txt_$cols[$i]' width='50' value='$v'></td>";
        }
        $s = "<table>$s</table><br/>";
        $s .= "<input type='submit' name='btnSubmit' value='Submit'>";
        $s .= "<input type='reset' value='Reset'>";
        //$s .= "<input type='submit' name='btnClear' value='Clear Form'>";
        return $s;
    }


    private function validate_password_rule($v) {
        return validate_password_rule($v); // in ../func/db.php.
    }

    //
    // $id - pkey of the entry to update.
    // $change_default - 1 if allow change default value, 0 otherwise.
    // 
    // If want to change value of default cols, set $change_default = 1 both here
    // and in function call to writeEditForm().
    //
    public function update($id, $change_default = 0) {
        $cols = $this->getDBTableColumnsAsArray($this->tbl_name);
        $ct = count($cols);
        $s = "";
        $v = "";
        for ($i = 0; $i < $ct; ++ $i) {
            // Pkey is autoincrement. Don't add to the new form.

            //if (in_array($cols[$i], $this->cols_hidden)) continue;
            if (! in_array($cols[$i], $this->fields)) continue;

            if ($cols[$i] == $this->tbl_pk ||
                ($change_default == 0 && in_array($cols[$i], $this->cols_default))) continue;
            //if (($change_default == 0 && in_array($cols[$i], $this->cols_default))) continue;
            $_s = $cols[$i];
            $_v = trim( $_REQUEST["txt_$cols[$i]"] );

            if ( in_array($cols[$i], $this->cols_pwd) ) {
                $_v2 = trim( $_REQUEST["txt_2_$cols[$i]"] );
                if ($_v == '' && $_v2 == '') continue; // if new password is empty, do not update.
                else if ($_v != $_v2) {
                    $msg = "<font color='red'>Error: The two entries for $cols[$i] should match.</font>";
                    return $msg; // break out of the loop and return.
                }
                else {
                    $e = $this->validate_password_rule($_v);
                    if ($e != "") {
                        $msg = "<font color='red'>Error: $e</font>";
                        return $msg;
                    }
                    else {
                        $_v = MD5($_v);
                    }
                }
            }

            $_v = db_encode( $_v );
            if ($s == "") {
                $s .= "$_s = $_v";
            } else {
                $s .= ", $_s = $_v";
            }
        }

        $query = "UPDATE $this->tbl_name SET $s WHERE $this->tbl_pk = $id";
        //echo "$query<br/>"; exit(0); // "..Test..";

        try {
            $result = mysql_query($query);
            if (! $result) {
                $msg = "<font color='red'>Error: " . mysql_error() . "</font>";
            } else {
                $msg = "<font color='green'>New entry is successfully added</font>";
            }
        } catch (Exception $e) {
            $msg = "<font color='red'>Error: " . $e->getMessage() . "</font>";
        }

        return $msg;
    }

    //
    // change_default: if 0, use database default. if 1, need to privide value.
    // extra_fields:   an array, provides values for hidden/default fields.
    //
    public function insertNew($change_default = 0, $extra_fields = array()) {
        $cols = $this->getDBTableColumnsAsArray($this->tbl_name);
        $ct = count($cols);
        $s = "";
        $v = "";
        for ($i = 0; $i < $ct; ++ $i) {
            // Pkey is autoincrement. Don't add to the new form.

            if (in_array($cols[$i], $this->cols_hidden)) continue;

            if ($cols[$i] == $this->tbl_pk ||
                ($change_default == 0 && in_array($cols[$i], $this->cols_default))) continue;
            $_s = $cols[$i];
            $_v = trim( $_REQUEST["txt_$cols[$i]"] );
            //if ( in_array($cols[$i], $this->cols_pwd) ) $_v = MD5($_v);

            if ( in_array($cols[$i], $this->cols_pwd) ) {
                $_v2 = trim( $_REQUEST["txt_2_$cols[$i]"] );
                if ($_v == '' && $_v2 == '') { // if new password is empty, do not insert.
                    $msg = "<font color='red'>Error: Password cannot be empty.</font>";
                    return $msg; // break out of the loop and return.
                }
                else if ($_v != $_v2) {
                    $msg = "<font color='red'>Error: The two entries for $cols[$i] should match.</font>";
                    return $msg; // break out of the loop and return.
                }
                else {
                    $e = $this->validate_password_rule($_v);
                    if ($e != "") {
                        $msg = "<font color='red'>Error: $e</font>";
                        return $msg;
                    }
                    else {
                        $_v = MD5($_v);
                    }
                }
            }

            $_v = db_encode( $_v );
            if ($s == "") {
                $s .= $_s;
                $v .= $_v;
            }
            else {
                $s .= ", $_s";
                $v .= ", $_v";
            }
        }

        $ct = count($extra_fields);
        for ($i = 0; $i < $ct; $i += 2) {
            $_s = $extra_fields[$i];
            $_v = db_encode($extra_fields[$i + 1]);
            if ($s == "") {
                $s .= $_s;
                $v .= $_v;
            }
            else {
                $s .= ", $_s";
                $v .= ", $_v";
            }
        }

        $query = "INSERT INTO $this->tbl_name ($s) VALUES ($v)";
        //echo "$query<br/>"; exit(0);

        try {
            $result = mysql_query($query);
            if (! $result) {
                $msg = ($this->_DEBUG ? mysql_error() : "mysql error");
                $msg = "<font color='red'>Error: $msg</font>";
            } else {
                $msg = "<font color='green'>New entry is successfully added</font>";
            }
        } catch (Exception $e) {
            $msg = ($this->_DEBUG ? $e->getMessage() : "mysql exception");
            $msg = "<font color='red'>Error: $msg</font>";
        }

        return $msg;
    }

    public function deleteEntry($ID) {
        $query = "DELETE FROM $this->tbl_name WHERE $this->tbl_pk = '$ID'";
        try {
            $result = mysql_query($query);
            if (! $result) {
                $msg = "<font color='red'>Error: " . mysql_error() . "</font>";
            } else {
                $msg = "<font color='green'>Entry is successfully deleted</font>";
            }
        } catch (Exception $e) {
            $msg = "<font color='red'>Error: " . $e->getMessage() . "</font>";
        }
        return $msg;
    }

    //
    // see function getDBTableAsHtmlTable() for the definition of doManage.
    //
    public function manage_table($tbl_name, $title, $fields = "*", $order = "DESC", $doManage = 0) {
        $query = "SELECT $fields FROM $tbl_name ORDER BY $this->tbl_pk $order";
        //$query = "select ID, first_name, last_name, email, login, gid from $tbl_name";
        print $this->getDBTableAsHtmlTable($query, $title, $doManage);
    }

    //
    // Return table columns as an array
    //
    public function getTableColumns($tbl_name) {
        //print str_replace("<", "&lt;", getDBTableAsXml("show columns from $tbl_name"));
        //print_r( getDBTableColumns($tbl_name) );
        print $this->getDBTableAsHtmlTable("show columns from $tbl_name", "$tbl_name Columns", 0);
    }

//
    public function getDBTableColumnsAsArray($tbl_name) {
        $query = "show columns from $tbl_name";
        global $link;
        $result = mysql_query($query, $link);
        if (! $result) {
            $this->doExit('Invalid query: ' . mysql_error());
        }

        $s = array();
        if (mysql_num_rows($result) > 0) {
            $i = 0;
            while ($info = mysql_fetch_array($result)) {
                array_push($s, $info['Field']);
            }
        }
        return $s;
    }

//
    public function getDBTableAsXml($query) {
        global $link;
        $result = mysql_query($query, $link);
        if (! $result) {
            $this->doExit('Invalid query: ' . mysql_error());
        }

        $s = "";
        if (mysql_num_rows($result) == 0) {
            $s = "";
        } else {
            $i = 0;
            while ($info = mysql_fetch_array($result)) {
                ++ $i;
                $s .= "<row>" . $this->writeRowAsXml($info) . "</row>";
            }
        }
        $s = "<?xml version=\"1.0\"?><root>$s</root>";

        return $s;
    }

//
    private function writeRowAsXml($a) {
        $s = "";
        $i = 0;
        foreach ($a as $key => $value) {
            ++ $i;
            $value = db_htmlEncode($value);
            if ($i % 2 == 0) $s .= "<$key>$value</$key>";
        }

        return $s;
    }

    // 
    // @params:
    //  - doManage: Is the XOR result of: 1 - add, 2 - edit, 4 - delete.
    // 
    public function getDBTableAsHtmlTable($query, $title, $doManage = 0) {
        global $link;
        $result = mysql_query($query, $link);
        if (! $result) {
            $this->doExit('Invalid query: ' . mysql_error());
        }

        $s = "";
        if (mysql_num_rows($result) == 0) {
            //$s = "(no entry yet)";
            $addNew = ($doManage & 1) ? " [ <a href='admin_tbl_add.php?tbl=$this->tbl_name'>Add New</a> ]" : "";
            $s = "<h3>$title</h3>$addNew<p><font color='green'>This table is empty</font></p>";
        } else {
            // Write header and first row.
            $info = mysql_fetch_array($result);
            $s .= $this->writeHdr($info, $doManage);

            $ct = 1;
            $s .= $this->writeRow($info, $doManage, $this->tr_style1);

            while ($info = mysql_fetch_array($result)) {
                //print_r($info); break;
                ++ $ct;
                $style = ($ct & 1) ? $this->tr_style1 : $this->tr_style2;
                $s .= $this->writeRow($info, $doManage, $style);
            }

            $addNew = ($doManage & 1) ? " [ <a href='admin_tbl_add.php?tbl=$this->tbl_name'>Add New</a> ]" : "";

            $s = "<h3>$title</h3>$addNew<p><table $this->tb_style>$s</table></p>";
        }
        return $s;
    }

    private function writeHdr($a, $doManage) {
        $ct = count($a);
        $i = 0;
        $s = "";

        if ($doManage & 2 || $doManage & 4) $s .= "<td><b>&nbsp;Action&nbsp;</b></td>";

        foreach ($a as $key => $value) {
            ++ $i;
            if ($i % 2 == 0) $s .= "<td><b>&nbsp;$key&nbsp;</b></td>";
        }
        return "<tr $this->th_style>$s</tr>";
    }

    private function writeRow($a, $doManage, $style="") {
        $ct = count($a);
        $s = "";

        if ($doManage & 2 || $doManage & 4) {
            $pk = $a[$this->tbl_pk];
            if ($doManage & 2) {
                $s .= "<td><a href='admin_tbl_edit.php?tbl=$this->tbl_name&pk=$pk'>Edit</a> ";
            }
            if ($doManage & 4) {
                $s .= "<a href='#', onclick='javascript:tbl_del(\"$this->tbl_name\", $pk);'>Delete</a></td>";
            }
        }
        for ($i = 0; $i < $ct; $i += 2) {
            //echo $i.",";
            $idx = $i / 2;
            $s .= "<td>&nbsp;$a[$idx]&nbsp;</td>";
        }
        $s = "<tr $style>$s</tr>";
        return $s;
    }

    private function doExit($msg) {
        die($msg . ". Please contact your system administrator.");
        exit();
    }

} // end of class.

?>
