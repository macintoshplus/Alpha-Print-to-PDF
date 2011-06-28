<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-1" />
<title>xmlTest</title>
</head>

<body>


<?php
$file = "XMLPrintModel.xmlpm";

$dataInfos = array();
$niveau=0;
$lastName;
$arrayNiveau;

function addData($name,$attribs,$niveau){

	global $dataInfos;
	global $arrayNiveau;
	
	//if($niveau >0 && $name==$arrayNiveau[$niveau]) $name=null;
	
	if($niveau==0)
	$dataInfos[$name]=$attribs;
	else if($niveau==1)
	$dataInfos[$arrayNiveau[1]][$name]=$attribs;
	else if($niveau==2)
	$dataInfos[$arrayNiveau[1]][$arrayNiveau[2]][$name]=$attribs;
	else if($niveau==3)
	$dataInfos[$arrayNiveau[1]][$arrayNiveau[2]][$arrayNiveau[3]][$name]=$attribs;
	else if($niveau==4)
	$dataInfos[$arrayNiveau[1]][$arrayNiveau[2]][$arrayNiveau[3]][$arrayNiveau[4]][$name]=$attribs;
	else if($niveau==5)
	$dataInfos[$arrayNiveau[1]][$arrayNiveau[2]][$arrayNiveau[3]][$arrayNiveau[4]][$arrayNiveau[5]][$name]=$attribs;
	else if($niveau==6)
	$dataInfos[$arrayNiveau[1]][$arrayNiveau[2]][$arrayNiveau[3]][$arrayNiveau[4]][$arrayNiveau[5]][$arrayNiveau[6]][$name]=$attribs;
	
}

function trustedFile($file)
{
    // faites seulement confiance aux fichiers locaux dont vous êtes le propriétaire
    if (!eregi("^([a-z]+)://", $file)
        && fileowner($file) == getmyuid()) {
            return true;
    }
    return false;
}

function startElement($parser, $name, $attribs)
{
	global $niveau;
	global $arrayNiveau;
	
	addData($name,$attribs,$niveau);
	
	$niveau++;
	$arrayNiveau[$niveau]=$name;
	//print_r($arrayNiveau);
	
	//$dataInfos[$name]['NIVEAU']=$niveau;
	
	
	
    echo "&lt;<font color=\"#0000cc\">$name</font>";
    if (count($attribs)) {
        foreach ($attribs as $k => $v) {
            echo " <font color=\"#009900\">$k</font>=\"<font
                   color=\"#990000\">",utf8_decode($v),"</font>\"";
        }
    }
    echo "&gt;";
}

function endElement($parser, $name)
{
	global $niveau;
	$niveau--;
    echo "&lt;/<font color=\"#0000cc\">$name</font>&gt;";
}

function characterData($parser, $data)
{
    echo "<strong>$data</strong>";
}

function PIHandler($parser, $target, $data)
{
    switch (strtolower($target)) {
        case "php":
            global $parser_file;
            // si le document analysé est de confiance, nous déclarons qu'il est sûr 
            // d'exécuter le code PHP qu'il contient. Si ce n'est pas le cas, le code est affiché
            // à la place.
            if (trustedFile($parser_file[$parser])) {
                eval($data);
            } else {
                printf("Untrusted PHP code: <em>%s</em>",
                        htmlspecialchars($data));
            }
            break;
    }
}

function defaultHandler($parser, $data)
{
    if (substr($data, 0, 1) == "&" && substr($data, -1, 1) == ";") {
        printf('<font color="#aa00aa">%s</font>',
                htmlspecialchars($data));
    } else {
        printf('<font size="-1">%s</font>',
                htmlspecialchars($data));
    }
}

function externalEntityRefHandler($parser, $openEntityNames, $base, $systemId,
                                  $publicId) {
    if ($systemId) {
        if (!list($parser, $fp) = new_xml_parser($systemId)) {
            printf("Could not open entity %s at %s\n", $openEntityNames,
                   $systemId);
            return false;
        }
        while ($data = fread($fp, 4096)) {
            if (!xml_parse($parser, $data, feof($fp))) {
                printf("erreur XML : %s à la ligne %d lors de l'analyse de l'entité %s\n",
                       xml_error_string(xml_get_error_code($parser)),
                       xml_get_current_line_number($parser), $openEntityNames);
                xml_parser_free($parser);
                return false;
            }
        }
        xml_parser_free($parser);
        return true;
    }
    return false;
}

function new_xml_parser($file)
{
    global $parser_file;

    $xml_parser = xml_parser_create();
    xml_parser_set_option($xml_parser, XML_OPTION_CASE_FOLDING, 1);
	xml_parser_set_option($xml_parser,XML_OPTION_TARGET_ENCODING, "UTF-8");
    xml_set_element_handler($xml_parser, "startElement", "endElement");
    xml_set_character_data_handler($xml_parser, "characterData");
    xml_set_processing_instruction_handler($xml_parser, "PIHandler");
    xml_set_default_handler($xml_parser, "defaultHandler");
    xml_set_external_entity_ref_handler($xml_parser, "externalEntityRefHandler");

    if (!($fp = @fopen($file, "r"))) {
        return false;
    }
    if (!is_array($parser_file)) {
        settype($parser_file, "array");
    }
    $parser_file[$xml_parser] = $file;
    return array($xml_parser, $fp);
}

if (!(list($xml_parser, $fp) = new_xml_parser($file))) {
    die("Impossible d'ouvrir le fichier XML");
}

echo "<pre>";
while ($data = fread($fp, 4096)) {
    if (!xml_parse($xml_parser, $data, feof($fp))) {
        die(sprintf("Erreur XML : %s à la ligne %d\n",
                    xml_error_string(xml_get_error_code($xml_parser)),
                    xml_get_current_line_number($xml_parser)));
    }
}
echo "</pre>";
echo "parse complete\n<br>";

echo "<pre>";
print_r($dataInfos);
echo "</pre>";

xml_parser_free($xml_parser);

?>


</body>
</html>
