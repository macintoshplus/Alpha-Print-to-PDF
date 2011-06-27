<?php
/******************************************/
/*           XMLPrintModel.php            */
/*   Moteur PHP de chargement de fichier  */
/*             XMLPrintModel              */
/*                                        */
/* *****************************************/

// Utilisable avec PHP 5 Seulement

class XMLPrintModel {
	
	var $classname ='XMLPrintModel';
	var $classversion = '0.0.1';
	
	var $PATH;
	
	
	var $Error=false;
	var $ErrorMsg="";
	
	var $dataInfos = array();
	var $niveau=0;
	var $lastName;
	var $arrayNiveau;
	
/******************************************/
/*                                        */
/*          Fonctions Publiques           */
/*                                        */
/* *****************************************/

	public function XMLPrintModel($path){ //Constructeur de la class
		$this->PATH=$path;
		if(!file_exists($this->PATH)){
			$this->ajouterErreur("Le fichier XML n'existe pas !");
			return;
		}
		$this->loadXMLFile();
		
	}
	
	public function version(){ //retourne la version du format de fichier
		/*echo "<pre>";
		print_r($this->dataInfos['XMLPM']['VERSION']);
		echo "</pre>";*/
		$version = $this->dataInfos['XMLPM']['VERSION'];
		if($version!="") return $version;
		return "1.0";
	}
	
	public function papier(){ //retourne le type de papier
		return $this->dataInfos['XMLPM']['PAPIER']['TYPE'];
	}
	public function orientation(){ //retourne 
		return $this->dataInfos['XMLPM']['ORIENTATION']['MODE'];
	}
	public function marge(){ //retourne 
		return $this->dataInfos['XMLPM']['MARGIN'];
	}
	
	public function pagination(){ //retourne un tableau contenant les infos de pagination
		return $this->dataInfos['XMLPM']['PAGINATION'];
	}
	
	
	public function premierePageEstActif(){ //retourne vrai si la page est active
		//echo "premierePageEstActif : ";
		if(!isset($this->dataInfos['XMLPM']['PAGES']['PREMIERE'])) return false;
		//echo "OK ";
		if(!isset($this->dataInfos['XMLPM']['PAGES']['PREMIERE']['ACTIVE'])) return false;
		//echo "OK ";
		return $this->dataInfos['XMLPM']['PAGES']['PREMIERE']['ACTIVE']=="1";
	}
	
	public function autrePageEstActif(){ //retourne vrai si la page est active
		if(!isset($this->dataInfos['XMLPM']['PAGES']['AUTRE'])) return false;
		if(!isset($this->dataInfos['XMLPM']['PAGES']['AUTRE']['ACTIVE'])) return false;
		return $this->dataInfos['XMLPM']['PAGES']['AUTRE']['ACTIVE']=="1";
	}
	
	public function dernierePageEstActif(){ //retourne vrai si la page est active
		if(!isset($this->dataInfos['XMLPM']['PAGES']['DERNIERE'])) return false;
		if(!isset($this->dataInfos['XMLPM']['PAGES']['DERNIERE']['ACTIVE'])) return false;
		return $this->dataInfos['XMLPM']['PAGES']['DERNIERE']['ACTIVE']=="1";
	}
	
	
	public function contenuPremierePage(){ //retourne vrai si la page est active
		if(!isset($this->dataInfos['XMLPM']['PAGES']['PREMIERE'])) {
			$this->ajouterErreur("La première page n'est pas définie !");
			return;
		}
		return $this->dataInfos['XMLPM']['PAGES']['PREMIERE'];
	}
	
	public function contenuAutrePage(){ //retourne vrai si la page est active
		if(!isset($this->dataInfos['XMLPM']['PAGES']['AUTRE'])) {
			$this->ajouterErreur("Les autres pages ne sont pas définie !");
			return;
		}
		return $this->dataInfos['XMLPM']['PAGES']['AUTRE'];
	}
	
	public function contenuDernierePage(){ //retourne vrai si la page est active
		if(!isset($this->dataInfos['XMLPM']['PAGES']['DERNIERE'])) {
			$this->ajouterErreur("La dernière page n'est pas définie !");
			return;
		}
		return $this->dataInfos['XMLPM']['PAGES']['DERNIERE'];
	}
	
	
	public function fixObject(){ //retourne un tableau contenant les objets fixes de la page
		//Ex : cadre et texte d'entete
	}
	
	public function otherObject(){ //retourne les elements variables de la page
		//Ex : tableau
		
	}
	
	public function error(){
		return $this->Error;
	}
	public function errorMsg(){
		return $this->ErrorMsg;
	}
	
	

/******************************************/
/*                                        */
/*          Fonctions Privées             */
/*                                        */
/* *****************************************/

	private function loadXMLFile(){ //charge le fichier XML
		$xml_parser = xml_parser_create();
		xml_parser_set_option($xml_parser, XML_OPTION_CASE_FOLDING, 1);
		xml_parser_set_option($xml_parser,XML_OPTION_TARGET_ENCODING, "UTF-8");
		xml_set_element_handler($xml_parser, array(&$this,"startElement"), array(&$this,"endElement"));
		//xml_set_character_data_handler($xml_parser, "characterData");
		//xml_set_processing_instruction_handler($xml_parser, "PIHandler");
		//xml_set_default_handler($xml_parser, "defaultHandler");
		//xml_set_external_entity_ref_handler($xml_parser, "externalEntityRefHandler");
	
		if (!($fp = @fopen($this->PATH, "r"))) {
			$this->ajouterErreur("Impossible d'ouvrir le fichier en lecture !");
			return false;
		}
		$data="";
		while ($data = fread($fp, 4096)) {
			if (!xml_parse($xml_parser, $data, feof($fp))) {
				$this->ajouterErreur(sprintf("Erreur XML : %s à la ligne %d colonne : %d\n",
							xml_error_string(xml_get_error_code($xml_parser)),
							xml_get_current_line_number($xml_parser),
							xml_get_current_column_number($xml_parser)));
				break 1;
			}
		}
		xml_parser_free($xml_parser);
		
		/*echo "<pre>";
		print_r($this->dataInfos);
		echo "</pre>";*/
			
	}
	private function ajouterErreur($txt){
		$this->Error=true;
		$this->ErrorMsg.=$txt."\n";
	
	
	}
	
	private function addData($name,$attribs,$niveau){
		
		//if($niveau >0 && $name==$this->arrayNiveau[$niveau]) $name=null;
		
		if($niveau==0)
		$this->dataInfos[$name]=$attribs;
		else if($niveau==1)
		$this->dataInfos[$this->arrayNiveau[1]][$name]=$attribs;
		else if($niveau==2)
		$this->dataInfos[$this->arrayNiveau[1]][$this->arrayNiveau[2]][$name]=$attribs;
		else if($niveau==3)
		$this->dataInfos[$this->arrayNiveau[1]][$this->arrayNiveau[2]][$this->arrayNiveau[3]][$name]=$attribs;
		else if($niveau==4)
		$this->dataInfos[$this->arrayNiveau[1]][$this->arrayNiveau[2]][$this->arrayNiveau[3]][$this->arrayNiveau[4]][$name]=$attribs;
		else if($niveau==5)
		$this->dataInfos[$this->arrayNiveau[1]][$this->arrayNiveau[2]][$this->arrayNiveau[3]][$this->arrayNiveau[4]][$this->arrayNiveau[5]][$name]=$attribs;
		else if($niveau==6)
		$this->dataInfos[$this->arrayNiveau[1]][$this->arrayNiveau[2]][$this->arrayNiveau[3]][$this->arrayNiveau[4]][$this->arrayNiveau[5]][$this->arrayNiveau[6]][$name]=$attribs;
		
	}
	
	private function startElement($parser, $name, $attribs)
	{
		
		$this->addData($name,$attribs,$this->niveau);
		
		$this->niveau++;
		$this->arrayNiveau[$this->niveau]=$name;
		

	}
	
	private function endElement($parser, $name)
	{
		$this->niveau--;
	}

}
?>