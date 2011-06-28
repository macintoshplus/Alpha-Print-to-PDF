<?php
/******************************************/
/*          AlphaPrintToPDF.php           */
/*       Moteur PHP d'impression          */
/*                                        */
/*  Ecrit par Jean-Baptiste Nahan         */
/*  contact : jbnahan@gmail.com           */
/*                                        */
/*           Licence CeCILL-v1            */
/*                                        */
/******************************************/

/* Ajout du 29/07/2010 : Ajout de la gestion des propriétés BOLD et ITALIC pour le texte. Dans le cas ou le nom de la police contient déjà les types proporiétés BOLD et/ou ITALIC elle seront directement utilisé. */

// Utilisable avec PHP 5 Seulement

include('pdfGenerator/class.ezpdf.php');
include('class/class.XMLPrintModel.php');

class AlphaPrintToPDF {
	
	var $classname ='AlphaPrintToPDF';
	var $classversion = '0.2.3';
	
	var $DATA;
	var $CONFIG;
	var $DEBUG=true;
	
	var $Error=false;
	var $ErrorMsg="";
	
	var $pdf;
	var $pdfHauteurMax;
	var $pdfHauteurMin;
	var $pdfLargeurMax;
	var $pdfLargeurMin;
	var $formatXML;
	var $XMLVer="0.0";
	
	var $ActivePage="None"; //permet de savoir ou en est le moteur de rendu (Premiere ; Autre ; Derniere)
	var $RenduLigneEncour=-1; //Index de la ligne en cours de rendu
	var $RenduHautLigne = 0 ; //Valeur de Y pour le rendu des lignes
	var $RepeatOther = false; //est à true si le tableau n'est pas fini et qu'il reste encore des données à afficher
	
/******************************************/
/*                                        */
/*          Fonctions Publiques           */
/*                                        */
/* *****************************************/

	public function AlphaPrintToPDF($config){ //Constructeur de la class
		
		$this->CONFIG=$config;
		
		if(!isset($config['file'])) {
			$this->ajouterErreur("Le fichier de sortie n'est pas défini !");
			return;
		}
		if(!isset($config['XMLFormatFile'])) {
			$this->ajouterErreur("Le fichier de format d'impression n'est pas défini !");
			return;
		}
		if(!isset($config['data'])) {
			$this->ajouterErreur("Les données ne sont pas défini !");
			return;
		}
		
		//if(isset($config['debug'])) $this->DEBUG=$config['debug'];
		
		$this->DATA=$this->CONFIG['data'];
		
		$this->chargerXMLFormat();
		if($this->Error) return;
		
		$this->createPDFFile();
		if($this->Error) return;
		
		$this->writePDFFile();
		if($this->Error) return;
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
	private function chargerXMLFormat(){
		
		$this->formatXML = & new XMLPrintModel($this->CONFIG['XMLFormatFile']);
		/*echo "<pre>";
		print_r($this->formatXML);
		echo "</pre>";*/
		if($this->formatXML->error()){ $this->ajouterErreur($this->formatXML->errorMsg()); return; }
		
		if($this->DEBUG) echo "Chargement version : ";
		$this->XMLVer=$this->formatXML->version();
		if($this->DEBUG) echo $this->XMLVer,"<br />";
		
	}
	private function createPDFFile(){
		$this->pdf = & new Cezpdf($this->formatXML->papier(), $this->formatXML->orientation()); // constructeur de la classe EZPDF
		
		$marge = $this->formatXML->marge();
		//echo "<br>W : ",$this->pdf->ez['pageWidth']," H : ",$this->pdf->ez['pageHeight'],"<br>";
		
		$this->pdfHauteurMax=$this->pdf->ez['pageHeight']-intval($marge['TOP']);
		$this->pdfHauteurMin=intval($marge['BOTTOM']);
		
		$this->pdfLargeurMax=$this->pdf->ez['pageWidth']-intval($marge['RIGHT']);
		$this->pdfLargeurMin=intval($marge['LEFT']);
		
		$this->pdf->ezSetMargins(intval($marge['TOP']),intval($marge['BOTTOM']),intval($marge['LEFT']),intval($marge['RIGHT']));
		
		//active la pagination si il y en a une
		$pagination = $this->formatXML->pagination();
		if(!isset($pagination['ACTIVE'])){
			$this->ajouterErreur("La balise de pagination 'ACTIVE' n'est pas correcte (".$this->CONFIG['XMLFormatFile'].")!");
			return;
		}
		if($pagination['ACTIVE']=="1"){
			if(!isset($pagination['FORMAT'])){
				$this->ajouterErreur("La balise de pagination 'FORMAT' n'est pas correcte !");
				return;
			}
			if(!isset($pagination['POSITION'])){
				$this->ajouterErreur("La balise de pagination 'POSITION' n'est pas correcte !");
				return;
			}
			if(!isset($pagination['X'])){
				$this->ajouterErreur("La balise de pagination 'X' n'est pas correcte !");
				return;
			}
			if(!isset($pagination['Y'])){
				$this->ajouterErreur("La balise de pagination 'Y' n'est pas correcte !");
				return;
			}
			if(!isset($pagination['TAILLE'])){
				$this->ajouterErreur("La balise de pagination 'TAILLE' n'est pas correcte !");
				return;
			}
			if(!isset($pagination['POLICE'])){
				$this->ajouterErreur("La balise de pagination 'POLICE' n'est pas correcte !");
				return;
			}
			if(!isset($pagination['COULEURPOLICE'])){
				$this->ajouterErreur("La balise de pagination 'COULEURPOLICE' n'est pas correcte !");
				return;
			}
			if(gettype($couleur=$this->traitementCouleur($pagination['COULEURPOLICE']))!="array"){
				$this->ajouterErreur("Pagination : Erreur sur COULEURPOLICE : ".$couleur." !");
				return;
			}
			
			$this->pdf->ezStartPageNumbers($this->ajusterPourMargeX(intval($pagination['X'])),$this->ajusterPourMargeY(intval($pagination['Y'])),intval($pagination['TAILLE']),'./pdfGenerator/fonts/'.$pagination['POLICE'].'.afm',floatval($couleur[0]),floatval($couleur[1]),floatval($couleur[2]),$pagination['POSITION'],$pagination['FORMAT']);
			
			
		}
		//Vérification utilisation de la première page
		if($this->formatXML->premierePageEstActif()){
			//echo "1er page active ! ";
			//dessine les element de la premiere page
			$this->ActivePage="Premiere";
			$contenuPage=$this->formatXML->contenuPremierePage();
			if($this->formatXML->error()){
				$this->ajouterErreur($this->formatXML->errorMsg());
				return;
			}
			/*echo "<pre>";
			print_r($contenuPage);
			echo "</pre>";*/
			$this->traitementContenu($contenuPage);
			$this->ActivePage="None";
			
		}
		if($this->error()) return;
		//Vérification utilisation autre page
		if($this->formatXML->autrePageEstActif()){
			while($this->RepeatOther==true){
				$this->ActivePage="Autre";
				$this->ajoutNouvellePage();
				//dessine les element des autres pages
				$contenuPage=$this->formatXML->contenuAutrePage();
				if($this->formatXML->error()){
					$this->ajouterErreur($this->formatXML->errorMsg());
					return;
				}
				/*echo "<pre>";
				print_r($contenuPage);
				echo "</pre>";*/
				$this->traitementContenu($contenuPage);
				$this->ActivePage="None";
			}
		}
		
		//Vérification utilisation dernière page
		if($this->formatXML->dernierePageEstActif()){
			$this->ActivePage="Derniere";
			$this->ajoutNouvellePage();
			//dessine les element de la derniere page
			$contenuPage=$this->formatXML->contenuDernierePage();
			if($this->formatXML->error()){
				$this->ajouterErreur($this->formatXML->errorMsg());
				return;
			}
			/*echo "<pre>";
			print_r($contenuPage);
			echo "</pre>";*/
			$this->traitementContenu($contenuPage);
			$this->ActivePage="None";
		}
	}
	
	private function writePDFFile(){
		$infos = array('Author'=>'Obj '.$this->classname.': v'.$this->classversion,
		'Title' => $this->CONFIG['Title'],
		'Subject' => $this->CONFIG['Subject'],
		'CreationDate' => date("d-m-Y",time()),
		'Keywords' => $this->CONFIG['keywords'],
		'Creator' => $this->CONFIG['pdfcreator'].' - Avec AlphaPrintToPDF',
		'Producer' => 'Alpha Print To PDF (v'.$this->classversion.')');
		$this->pdf->addInfo($infos);
		if(!$fichier=@fopen($this->CONFIG['file'],"w")){
			$this->ajouterErreur("Impossible d'ouvrir le fichier en ecriture! Contactez l'administrateur!");
			return;
		}
		if (fwrite($fichier, $this->pdf->ezOutput()) === FALSE) {
			$this->ajouterErreur("Impossible d'écrire dans le fichier (".$this->CONFIG['file'].")");
			return;
		}
		fclose($fichier);
	}
	
	private function traitementContenu($contenuPage){
		
		if($this->DEBUG) echo "<pre>",print_r($contenuPage, true),"</pre>";
		
		foreach($contenuPage as $nomObjet => $proprietes){
			if(isset($proprietes['TYPE'])){
				if(strtoupper($proprietes['TYPE'])=="TRAIT"){
					$this->ajouterTrait($proprietes);
					if($this->error()) break 1;
				}
				if(strtoupper($proprietes['TYPE'])=="TEXTE"){
					$this->ajouterTexte($proprietes);
					if($this->error()) break 1;
				}
				if(strtoupper($proprietes['TYPE'])=="RECTANGLE"){
					$this->ajouterRectangle($proprietes);
					if($this->error()) break 1;
				}
				if(strtoupper($proprietes['TYPE'])=="TABLEAU"){
					$this->ajouterTableau($proprietes);
					if($this->error()) break 1;
				}
			}
			
		}
	
	}
	
	
	private function ajouterTrait($propriete){
		if(!isset($propriete['TYPE'])){
			$this->ajouterErreur("AjouterTrait : Erreur la propriété TYPE n'est pas définie !");
			return;
		}
		if(strtoupper($propriete['TYPE'])!="TRAIT"){
			$this->ajouterErreur("AjouterTrait : Erreur la propriété TYPE n'est pas TRAIT !");
			return;
		}
		if(!isset($propriete['X'])){
			$this->ajouterErreur("AjouterTrait : Erreur la propriété X n'est pas définie !");
			return;
		}
		if(!isset($propriete['Y'])){
			$this->ajouterErreur("AjouterTrait : Erreur la propriété Y n'est pas définie !");
			return;
		}
		if(!isset($propriete['XFIN'])){
			$this->ajouterErreur("AjouterTrait : Erreur la propriété XFIN n'est pas définie !");
			return;
		}
		if(!isset($propriete['YFIN'])){
			$this->ajouterErreur("AjouterTrait : Erreur la propriété YFIN n'est pas définie !");
			return;
		}
		if(!isset($propriete['EPAISSEUR'])){
			$this->ajouterErreur("AjouterTrait : Erreur la propriété EPAISSEUR n'est pas définie !");
			return;
		}
		if(!isset($propriete['COULEUR'])){
			$this->ajouterErreur("AjouterTrait : Erreur la propriété COULEUR n'est pas définie !");
			return;
		}
		$couleur=$this->traitementCouleur($propriete['COULEUR']);
		if(gettype($couleur)!="array"){
			$this->ajouterErreur("AjouterTrait : Erreur sur COULEUR : ".$couleur." (".$propriete['COULEUR'].") !");
			return;
		}
		
		$this->pdf->setStrokeColor(floatval($couleur[0]),floatval($couleur[1]),floatval($couleur[2]));
		$this->pdf->setLineStyle(intval($propriete['EPAISSEUR']));
		
		//détermination de la position d'origine
		$x1=$this->ajusterPourMargeX(intval($propriete['X']));
		
		$y1=$this->ajusterPourMargeY(intval($propriete['Y']));
		
		$x2=$this->ajusterPourMargeX(intval($propriete['XFIN']));
		
		$y2=$this->ajusterPourMargeY(intval($propriete['YFIN']));
		
		
		$this->pdf->line($x1,$y1,$x2,$y2);
		
	}
	
	private function ajouterRectangle($propriete){
	
		if($this->DEBUG) echo "<b>Propriétés rectangle</b><br>";
		if($this->DEBUG) echo "<pre>".print_r($propriete, true)."</pre><br>";
		
		
		if(!isset($propriete['TYPE'])){
			$this->ajouterErreur("ajouterRectangle : Erreur la propriété TYPE n'est pas définie !");
			return;
		}
		if(strtoupper($propriete['TYPE'])!="RECTANGLE"){
			$this->ajouterErreur("ajouterRectangle : Erreur la propriété TYPE n'est pas RECTANGLE !");
			return;
		}
		if(!isset($propriete['X'])){
			$this->ajouterErreur("ajouterRectangle : Erreur la propriété X n'est pas définie !");
			return;
		}
		if(!isset($propriete['Y'])){
			$this->ajouterErreur("ajouterRectangle : Erreur la propriété Y n'est pas définie !");
			return;
		}
		if(!isset($propriete['LARGEUR'])){
			$this->ajouterErreur("ajouterRectangle : Erreur la propriété LARGEUR n'est pas définie !");
			return;
		}
		if(!isset($propriete['HAUTEUR'])){
			$this->ajouterErreur("ajouterRectangle : Erreur la propriété HAUTEUR n'est pas définie !");
			return;
		}
		if(!isset($propriete['EPAISSEURCONTOUR'])){
			$this->ajouterErreur("ajouterRectangle : Erreur la propriété EPAISSEURCONTOUR n'est pas définie !");
			return;
		}
		
		if($this->XMLVer=="1.0"){
		
			if(!isset($propriete['COULEURCONTOUR'])){
				$this->ajouterErreur("ajouterRectangle : Erreur la propriété COULEURCONTOUR n'est pas définie !");
				return;
			}
			$couleurContour=$this->traitementCouleur($propriete['COULEURCONTOUR']);
			if(gettype($couleurContour)!="array"){
				$this->ajouterErreur("ajouterRectangle : Erreur sur COULEURCONTOUR : ".$couleurContour." (".$propriete['COULEURCONTOUR'].") !");
				return;
			}
			
			if(!isset($propriete['COULEURCONTOUR1'])){
				$this->ajouterErreur("ajouterRectangle : Erreur la propriété COULEURCONTOUR1 n'est pas définie !");
				return;
			}
			$couleurContour1=$this->traitementCouleur($propriete['COULEURCONTOUR1']);
			if(gettype($couleurContour1)!="array"){
				$this->ajouterErreur("ajouterRectangle : Erreur sur COULEURCONTOUR1 : ".$couleurContour1." (".$propriete['COULEURCONTOUR1'].") !");
				return;
			}
		
		}
		else if($this->XMLVer=="1.1"){ //1.1
			/* Ajout du 30-07-2010 */
			
			// Bord Haut
			if(!isset($propriete['COULEURCONTOURHAUT'])){
				$this->ajouterErreur("ajouterRectangle : Erreur la propriété COULEURCONTOURHAUT n'est pas définie !");
				return;
			}
			$couleurContourHaut=$this->traitementCouleur($propriete['COULEURCONTOURHAUT']);
			if(gettype($couleurContourHaut)!="array"){
				$this->ajouterErreur("ajouterRectangle : Erreur sur COULEURCONTOURHAUT : ".$couleurContourHaut." (".$propriete['COULEURCONTOURHAUT'].") !");
				return;
			}
			if(!isset($propriete['CONTOURHAUTVISIBLE'])){
				$this->ajouterErreur("ajouterRectangle : Erreur la propriété CONTOURHAUTVISIBLE n'est pas définie !");
				return;
			}
			
			// Bord droit
			if(!isset($propriete['COULEURCONTOURDROITE'])){
				$this->ajouterErreur("ajouterRectangle : Erreur la propriété COULEURCONTOURDROITE n'est pas définie !");
				return;
			}
			$couleurContourDroite=$this->traitementCouleur($propriete['COULEURCONTOURDROITE']);
			if(gettype($couleurContourDroite)!="array"){
				$this->ajouterErreur("ajouterRectangle : Erreur sur COULEURCONTOURDROITE : ".$couleurContourDroite." (".$propriete['COULEURCONTOURDROITE'].") !");
				return;
			}
			if(!isset($propriete['CONTOURDROITEVISIBLE'])){
				$this->ajouterErreur("ajouterRectangle : Erreur la propriété CONTOURDROITEVISIBLE n'est pas définie !");
				return;
			}
			
			// Bord Bas
			if(!isset($propriete['COULEURCONTOURBAS'])){
				$this->ajouterErreur("ajouterRectangle : Erreur la propriété COULEURCONTOURBAS n'est pas définie !");
				return;
			}
			$couleurContourBas=$this->traitementCouleur($propriete['COULEURCONTOURBAS']);
			if(gettype($couleurContourBas)!="array"){
				$this->ajouterErreur("ajouterRectangle : Erreur sur COULEURCONTOURBAS : ".$couleurContourBas." (".$propriete['COULEURCONTOURBAS'].") !");
				return;
			}
			if(!isset($propriete['CONTOURBASVISIBLE'])){
				$this->ajouterErreur("ajouterRectangle : Erreur la propriété CONTOURBASVISIBLE n'est pas définie !");
				return;
			}
			
			// Bord gauche
			if(!isset($propriete['COULEURCONTOURGAUCHE'])){
				$this->ajouterErreur("ajouterRectangle : Erreur la propriété COULEURCONTOURGAUCHE n'est pas définie !");
				return;
			}
			$couleurContourGauche=$this->traitementCouleur($propriete['COULEURCONTOURGAUCHE']);
			if(gettype($couleurContourGauche)!="array"){
				$this->ajouterErreur("ajouterRectangle : Erreur sur COULEURCONTOURGAUCHE : ".$couleurContourGauche." (".$propriete['COULEURCONTOURGAUCHE'].") !");
				return;
			}
			if(!isset($propriete['CONTOURGAUCHEVISIBLE'])){
				$this->ajouterErreur("ajouterRectangle : Erreur la propriété CONTOURGAUCHEVISIBLE n'est pas définie !");
				return;
			}
			
			
			/* FIN Ajout du 30-07-2010 */
			
		}
		
		if(!isset($propriete['COULEURFOND'])){
			$this->ajouterErreur("ajouterRectangle : Erreur la propriété COULEURFOND n'est pas définie !");
			return;
		}
		if($propriete['COULEURFOND']<>"-1"){
			//echo "propriete['COULEURFOND'] = ",$propriete['COULEURFOND'];
			$couleurFond=$this->traitementCouleur($propriete['COULEURFOND']);
			if(gettype($couleurFond)!="array"){
				$this->ajouterErreur("ajouterRectangle : Erreur sur COULEURFOND : ".$couleurFond." (".$propriete['COULEURFOND'].") !");
				return;
			}
			//echo "R = '",$couleurFond[0],"' V = '",$couleurFond[1],"' B = '",$couleurFond[2],"'";
			
		}
		
		//détermination de la position d'origine
		$x1=$this->ajusterPourMargeX(intval($propriete['X']));
		
		$y1=$this->ajusterPourMargeY(intval($propriete['Y'])+intval($propriete['HAUTEUR']));
		
		
		
		//déssiner le fond
		if($propriete['COULEURFOND']<>"-1"){
			$this->pdf->setColor(floatval($couleurFond[0]),floatval($couleurFond[1]),floatval($couleurFond[2]));
			$this->pdf->filledRectangle($x1,$y1,intval($propriete['LARGEUR']),intval($propriete['HAUTEUR']));
		}
		
		//dessiner la bordure
		if($this->XMLVer=="1.0"){
			if($propriete['EPAISSEURCONTOUR']>0){
				$this->pdf->setStrokeColor(floatval($couleurContour[0]),floatval($couleurContour[1]),floatval($couleurContour[2]));
				$this->pdf->setLineStyle(intval($propriete['EPAISSEURCONTOUR']));
				$this->pdf->rectangle($x1,$y1,intval($propriete['LARGEUR']),intval($propriete['HAUTEUR']));
			}
		}else if($this->XMLVer=="1.1"){
			if($propriete['EPAISSEURCONTOUR']>0){ //si il y a de l'épaisseur
				
				//réglage de l'épaisseur du bord
				$this->pdf->setLineStyle(intval($propriete['EPAISSEURCONTOUR']),'square');
				
				//Bord haut
				if($propriete['CONTOURHAUTVISIBLE']=='true'){
					$this->pdf->setStrokeColor(floatval($couleurContourHaut[0]),floatval($couleurContourHaut[1]),floatval($couleurContourHaut[2]));
					//$this->pdf->line($x1,$y1,$x1+intval($propriete['LARGEUR']),$y1);
					$this->pdf->line($x1,$y1+intval($propriete['HAUTEUR']),$x1+intval($propriete['LARGEUR']),$y1+intval($propriete['HAUTEUR']));
				}
				//Bord droit
				if($propriete['CONTOURDROITEVISIBLE']=='true'){
					$this->pdf->setStrokeColor(floatval($couleurContourDroite[0]),floatval($couleurContourDroite[1]),floatval($couleurContourDroite[2]));
					$this->pdf->line($x1+intval($propriete['LARGEUR']),$y1,$x1+intval($propriete['LARGEUR']),$y1+intval($propriete['HAUTEUR']));
				}
				//Bord bas
				if($propriete['CONTOURBASVISIBLE']=='true'){
					$this->pdf->setStrokeColor(floatval($couleurContourBas[0]),floatval($couleurContourBas[1]),floatval($couleurContourBas[2]));
					//$this->pdf->line($x1,$y1+intval($propriete['HAUTEUR']),$x1+intval($propriete['LARGEUR']),$y1+intval($propriete['HAUTEUR']));
					$this->pdf->line($x1,$y1,$x1+intval($propriete['LARGEUR']),$y1);
				}
				//Bord gauche
				if($propriete['CONTOURGAUCHEVISIBLE']=='true'){
					$this->pdf->setStrokeColor(floatval($couleurContourGauche[0]),floatval($couleurContourGauche[1]),floatval($couleurContourGauche[2]));
					$this->pdf->line($x1,$y1,$x1,$y1+intval($propriete['HAUTEUR']));
				}
			}
		}
	}
	
	
	private function ajouterTexte($propriete){
	
		if(!isset($propriete['TYPE'])){
			$this->ajouterErreur("ajouterTexte : Erreur la propriété TYPE n'est pas définie !");
			return;
		}
		if(strtoupper($propriete['TYPE'])!="TEXTE"){
			$this->ajouterErreur("ajouterTexte : Erreur la propriété TYPE n'est pas TRAIT !");
			return;
		}
		if(!isset($propriete['X'])){
			$this->ajouterErreur("ajouterTexte : Erreur la propriété X n'est pas définie !");
			return;
		}
		if(!isset($propriete['Y'])){
			$this->ajouterErreur("ajouterTexte : Erreur la propriété Y n'est pas définie !");
			return;
		}
		if(!isset($propriete['POLICE'])){
			$this->ajouterErreur("ajouterTexte : Erreur la propriété POLICE n'est pas définie !");
			return;
		}
		if(!isset($propriete['TAILLE'])){
			$this->ajouterErreur("ajouterTexte : Erreur la propriété TAILLE n'est pas définie !");
			return;
		}
		if(!isset($propriete['VALEUR'])){
			$this->ajouterErreur("ajouterTexte : Erreur la propriété VALEUR n'est pas définie !");
			return;
		}
		if(!isset($propriete['COULEUR'])){
			$this->ajouterErreur("ajouterTexte : Erreur la propriété COULEUR n'est pas définie !");
			return;
		}
		if(!isset($propriete['ALIGN'])){
			$this->ajouterErreur("ajouterTexte : Erreur la propriété ALIGN n'est pas définie !");
			return;
		}
		if(!isset($propriete['LARGEUR'])){
			$this->ajouterErreur("ajouterTexte : Erreur la propriété LARGEUR n'est pas définie !");
			return;
		}
		if(!isset($propriete['HAUTEUR'])){
			$this->ajouterErreur("ajouterTexte : Erreur la propriété HAUTEUR n'est pas définie !");
			return;
		}
		if(gettype($couleur=$this->traitementCouleur(utf8_decode($propriete['COULEUR'])))!="array"){
			$this->ajouterErreur("ajouterTexte : Erreur sur COULEUR : ".$couleur." !");
			return;
		}
		
		/** Ajout du 29-07-2010 **/
		if((isset($propriete['COULEURFOND']) || isset($propriete['EPAISSEURCONTOUR'])) && ($propriete['COULEURFOND']<>"-1" || $propriete['EPAISSEURCONTOUR']>0)){
			//Appel le dessin du cadre pour faire la fond ou le contour.
			$pe['COULEURFOND']=$propriete['COULEURFOND'];
			$pe['EPAISSEURCONTOUR']=$propriete['EPAISSEURCONTOUR'];
			if($this->XMLVer=="1.0"){
				$pe['COULEURCONTOUR']=$propriete['COULEURCONTOUR'];
				$pe['COULEURCONTOUR1']=$propriete['COULEURCONTOUR1'];
			}
			else if($this->XMLVer=="1.1"){ //1.1
				$pe['COULEURCONTOURHAUT']=$propriete['COULEURCONTOURHAUT'];
				$pe['CONTOURHAUTVISIBLE']=$propriete['CONTOURHAUTVISIBLE'];
				$pe['COULEURCONTOURDROITE']=$propriete['COULEURCONTOURDROITE'];
				$pe['CONTOURDROITEVISIBLE']=$propriete['CONTOURDROITEVISIBLE'];
				$pe['COULEURCONTOURBAS']=$propriete['COULEURCONTOURBAS'];
				$pe['CONTOURBASVISIBLE']=$propriete['CONTOURBASVISIBLE'];
				$pe['COULEURCONTOURGAUCHE']=$propriete['COULEURCONTOURGAUCHE'];
				$pe['CONTOURGAUCHEVISIBLE']=$propriete['CONTOURGAUCHEVISIBLE'];
			}
			$pe['X']=$propriete['X'];
			$pe['Y']=$propriete['Y'];
			$pe['HAUTEUR']=$propriete['HAUTEUR'];
			$pe['LARGEUR']=$propriete['LARGEUR'];
			$pe['TYPE']="RECTANGLE";
			$pe['EXP']="TEXTE";
			$this->ajouterRectangle($pe);
		}
		/** FIN Ajout du 29-07-2010 **/
		
		
		$this->pdf->setColor(floatval($couleur[0]),floatval($couleur[1]),floatval($couleur[2]));
		
		/** Ajout du 29-07-2010 **/
		
		//variable contenant le complement du nom de la police
		$ajout_forme="";
		
		$e=explode("-", $propriete['POLICE']);
		if($e[0]==$propriete['POLICE']){
			
			//Variable des noms d'attribut :
			$a_bold="Bold";
			$a_italic="Oblique";
			
			//Si c'est Times la police normale est Times-Roman et changement des noms d'attribut
			if($propriete['POLICE']=='Times'){
				$ajout_forme="Roman";
				$a_italic="Italic";
			}
			
			//Gestion des propriétés Gras et Italique
			//if(!($propriete['BOLD']=='false' && $propriete['italic']=='false'))
			$ajout_forme=(($propriete['BOLD']=='true')? $a_bold:"").(($propriete['ITALIC']=='true')? $a_italic:"");
			//Si il y a une forme il ajoute le Tirer
			$ajout_forme=($ajout_forme!='')? "-".$ajout_forme:"";
		}
		/** FIN Ajout du 29-07-2010 **/
		
		if($this->DEBUG) echo ("ajouterTexte : Police :  ".$propriete['POLICE']." Ajout : ".$ajout_forme." !<br>");
		
		$this->pdf->selectFont($this->CONFIG['fontPath'].$propriete['POLICE'].$ajout_forme.'.afm');
		
		//ajustement de Y
		$hauteurPolice = $this->pdf->getFontHeight(intval($propriete['TAILLE']));
		$decentePolice = -$this->pdf->getFontDecender(intval($propriete['TAILLE']));
		$lieuxBaseLine = (intval($propriete['HAUTEUR'])/3)*2;
		//if($hauteurPolice<=intval($propriete['HAUTEUR'])){
			$realPosY = intval($propriete['Y'])+$lieuxBaseLine;//-((intval($propriete['HAUTEUR'])/2)-($hauteurPolice/2));//(($hauteurPolice)/2)+$decentePolice;
		//}else{
		//	$realPosY = intval($propriete['Y'])+($hauteurPolice)+(((intval($propriete['HAUTEUR']) - $hauteurPolice)/3)*2);//-$this->pdf->getFontDecender(intval($propriete['TAILLE'])));
		//}
		//rechercher remplacement des eventuels variable dans les données
		$texteAAfficher = $this->replaceVariableParData(utf8_decode($propriete['VALEUR']));//
		
		//données pour le calcul
		$largeurTexte=$this->pdf->getTextWidth(intval($propriete['TAILLE']),$texteAAfficher);
		$largeurCadre=intval($propriete['LARGEUR']);
		
		//réglage de l'alignement
		if(intval($propriete['ALIGN'])==0){ //gauche (par défaut)
			$positionAjuste = 0;
		}
		if(intval($propriete['ALIGN'])==1){ //centrer
			$positionAjuste = ($largeurCadre/2)-($largeurTexte/2);
		}
		if(intval($propriete['ALIGN'])==2){ //droite
			$positionAjuste = ($largeurCadre)-($largeurTexte);
		}
		
		$this->pdf->addText($this->ajusterPourMargeX(intval($propriete['X'])+$positionAjuste),$this->ajusterPourMargeY($realPosY),intval($propriete['TAILLE']),$texteAAfficher);
		
		//$this->pdf->setColor(0,0,0);
	
	}
	
	
	private function ajouterTableau($propriete){
		if($this->DEBUG) {
			echo "<br>Infos du Tableau : <br><pre>";
			print_r($propriete);
			echo "</pre>";
		}
		
		//chargement dans un tableau des colonnes
		$tmpArray = array();
		$tmpOrder = array();
		$max = intval($propriete['NBCOLUMN']);
		for($i=1;$i<=$max;$i++){
			$tmpArray[$i-1] = $propriete['COLONNE'.$i];
			$tmpOrder[$i-1] = intval($propriete['COLONNE'.$i]['ORDRE']);
		}
		
		$ColonneArray = $this->triTableauSelonOrdre($tmpArray,$tmpOrder);
		if($this->DEBUG) {
			echo "<br>Infos des Colonnes : <br><pre>";
			print_r($ColonneArray);
			echo "</pre>";
		}
		$tmpArray = array();
		$tmpOrder = array();
		$max = intval($propriete['NBROWS']);
		for($i=1;$i<=$max;$i++){
			$tmpArray[$i-1] = $propriete['LIGNE'.$i];
			$tmpOrder[$i-1] = intval($propriete['LIGNE'.$i]['ORDRE']);
		}
		
		$LigneArray = $this->triTableauSelonOrdre($tmpArray,$tmpOrder);
		if($this->DEBUG) {
			echo "<br>Infos des Lignes : <br><pre>";
			print_r($LigneArray);
			echo "</pre>";
		}
		
		//gestion de l'entête
		if(($this->ActivePage=="Premiere" && $propriete['HEADERROW']=="true") || ($this->ActivePage=="Autre" && $propriete['HEADERROW']=="true" && $propriete['REAPEATHEADROWS']=="true")){
			if(count($LigneArray)>0){ //vérifie qu'il y ait au moins une ligne
				
				if($LigneArray[0]['FONDVISIBLE']=="true"){//remplissage des propriété du cadre
					$infFond['TYPE']="RECTANGLE";
					$infFond['EXP']="TABLEAU";
					$infFond['X']=$propriete['X'];
					$infFond['Y']=$propriete['Y'];
					$infFond['LARGEUR']=$propriete['LARGEUR'];
					$infFond['HAUTEUR']=$LigneArray[0]['HAUTEUR'];
					//Désactive le contour
					$infFond['EPAISSEURCONTOUR']="0";
					if($this->XMLVer=="1.0"){
						$infFond['COULEURCONTOUR']="0,0,0";
						$infFond['COULEURCONTOUR1']="0,0,0";
					}else if($this->XMLVer=="1.1"){//1.1
						$infFond['COULEURCONTOURHAUT']="0,0,0";
						$infFond['CONTOURHAUTVISIBLE']="false";
						$infFond['COULEURCONTOURDROITE']="0,0,0";
						$infFond['CONTOURDROITEVISIBLE']="false";
						$infFond['COULEURCONTOURBAS']="0,0,0";
						$infFond['CONTOURBASVISIBLE']="false";
						$infFond['COULEURCONTOURGAUCHE']="0,0,0";
						$infFond['CONTOURGAUCHEVISIBLE']="false";
					}
					$infFond['COULEURFOND']=$LigneArray[0]['COULEURFOND'];
					$this->ajouterRectangle($infFond);
				}
				
				$realX=intval($propriete['X']);
				for($i=0;$i<count($ColonneArray);$i++){//parcour des colonnes pour afficher les valeurs
					$infTxt['TYPE']="TEXTE";
					$infTxt['X']=$realX;
					$infTxt['Y']=$propriete['Y'];
					$infTxt['POLICE']=$LigneArray[0]['NOMPOLICE'];
					$infTxt['TAILLE']=$LigneArray[0]['TAILLEPOLICE'];
					
					$e=explode("$",$ColonneArray[$i]['ENTETEDONNEE']);
					if(count($e)==1) $infTxt['VALEUR']=($ColonneArray[$i]['ENTETEDONNEE']); //pas de variable
					else $infTxt['VALEUR']=$ColonneArray[$i]['ENTETEDONNEE']."[0][".$ColonneArray[$i]['NOMDELIGNE']."]"; //variable
					
					$infTxt['COULEUR']=$LigneArray[0]['COULEURPOLICE'];
					
					$infTxt['LARGEUR']=sprintf("%d",intval($ColonneArray[$i]['LARGEUR'])-(($ColonneArray[$i]['BORDDROIT']=="true")? (intval($ColonneArray[$i]['TAILLEBORDURE'])+1):1));
					
					$infTxt['HAUTEUR']=$LigneArray[0]['HAUTEUR'];
					$infTxt['ALIGN']=$ColonneArray[$i]['ENTETEALIGNEMENT'];
					
					$infTxt['BOLD']=($LigneArray[0]['BOLD']=='true')? "true":"false";
					$infTxt['ITALIC']=($LigneArray[0]['ITALIC']=='true')? "true":"false";
					$infTxt['UNDERLINE']=($LigneArray[0]['UNDERLINE']=='true')? "true":"false";
					
					/*echo "<br>Infos TEXTE : <br><pre>";
					print_r($infTxt);
					echo "</pre>";*/
					
					$this->ajouterTexte($infTxt);
					//ajoute la largeur de la colonne à X pour changer de colonne
					$realX += intval($ColonneArray[$i]['LARGEUR']);
				}
				
				//bordure haute
				if($LigneArray[0]['AFFICHERBORDHAUT']=="true"){ //il cert à rien car il n'est pas visible
					$infLigne['TYPE']='TRAIT';
					$infLigne['X']=$propriete['X'];
					$infLigne['Y']=$propriete['Y'];
					$infLigne['XFIN']=sprintf("%d",intval($propriete['X'])+intval($propriete['LARGEUR']));
					$infLigne['YFIN']=$propriete['Y'];
					$infLigne['EPAISSEUR']=$LigneArray[0]['EPAISSEURBORDHAUT'];
					$infLigne['COULEUR']=$LigneArray[0]['COULEURBORDHAUT'];
					/*echo "<br>Infos du Trait Haut : <br><pre>";
					print_r($infLigne);
					echo "</pre>";*/
					$this->ajouterTrait($infLigne);
					//$infLigne['']=;
				}
				
				//bordure basse
				if($LigneArray[0]['AFFICHERBORDBAS']=="true"){ //il cert à rien car il n'est pas visible
					$infLigne['TYPE']='TRAIT';
					$infLigne['X']=$propriete['X'];
					$infLigne['Y']=sprintf("%d",intval($propriete['Y'])+intval($LigneArray[0]['HAUTEUR']));
					$infLigne['XFIN']=sprintf("%d",intval($propriete['X'])+intval($propriete['LARGEUR']));
					$infLigne['YFIN']=sprintf("%d",intval($propriete['Y'])+intval($LigneArray[0]['HAUTEUR']));
					$infLigne['EPAISSEUR']=$LigneArray[0]['EPAISSEURBORDBAS'];
					$infLigne['COULEUR']=$LigneArray[0]['COULEURBORDBAS'];
					/*echo "<br>Infos du Trait Haut : <br><pre>";
					print_r($infLigne);
					echo "</pre>";*/
					$this->ajouterTrait($infLigne);
					//$infLigne['']=;
				}
				
			}
		}
		
		
		//Affichage des données
		if($this->DEBUG) echo "<b>Affichage des lignes</b><br>";
		//remet la valeur haut au début
		if($this->RenduHautLigne==0){ 
			$this->RenduHautLigne=intval($propriete['Y']);
			//Ajoute la hauteur de l'entête
			if(($this->ActivePage=="Premiere" && $propriete['HEADERROW']=="true") || ($this->ActivePage=="Autre" && $propriete['HEADERROW']=="true" && $propriete['REAPEATHEADROWS']=="true")) $this->RenduHautLigne+=intval($LigneArray[0]['HAUTEUR']);
		}
		/*echo "<br>Données : <br><pre>";
		print_r($this->replaceVariableParData($propriete['DONNEE'],true));
		echo "</pre>";*/
		
		
		//recherche des max
		$max=count($this->replaceVariableParData(($propriete['DONNEE']),true));
		$maxY = intval($propriete['Y'])+intval($propriete['HAUTEUR']);
		
		$startAt=($this->RenduLigneEncour==-1)? 0:$this->RenduLigneEncour;
		
		if($this->DEBUG) echo "RenduHautLigne=",$this->RenduHautLigne," max=",$max," maxY=",$maxY," startAt=",$startAt,"<br>";
		
		//parcour des lignes de données pour afficher
		for($this->RenduLigneEncour=$startAt;$this->RenduLigneEncour<$max;$this->RenduLigneEncour++){
			if($this->RenduHautLigne>=$maxY){
				$this->RepeatOther=true;
				break 1;
			}
			//demande le type de ligne
			$TypeLigne = $this->replaceVariableParData(sprintf("%s[%d][TypeLigne]"
																		,utf8_decode($propriete['DONNEE'])
																		,$this->RenduLigneEncour));
			if($this->DEBUG) echo "<b>Type de Ligne : ",$TypeLigne,"</b><br>";
			//recherche de la ligne corresondante
			$indexLigneIs = -1;
			for($i=0;$i<count($LigneArray);$i++){
				if($TypeLigne==$LigneArray[$i]['TYPELIGNE']) $indexLigneIs = $i;
			}
			
			if($indexLigneIs>-1){ //affiche que si le type de ligne voulu est trouvé
				
				//annule l'affichage si la hauteur restante est inférieure à la hauteur de la ligne
				if(($maxY-$this->RenduHautLigne)<intval($LigneArray[$indexLigneIs]['HAUTEUR'])){
					$this->RepeatOther=true;
					break 1;
				}
				
				if($LigneArray[$indexLigneIs]['FONDVISIBLE']=="true"){//remplissage des propriété du cadre
					$infFond['TYPE']="RECTANGLE";
					$infFond['EXP']="TABLEAU_".$this->XMLVer;
					$infFond['X']=intval($propriete['X']);
					$infFond['Y']=$this->RenduHautLigne;
					$infFond['LARGEUR']=$propriete['LARGEUR'];
					$infFond['HAUTEUR']=$LigneArray[$indexLigneIs]['HAUTEUR'];
					//désactive le contour
					$infFond['EPAISSEURCONTOUR']="0";
					if($this->XMLVer=="1.0"){
						$infFond['COULEURCONTOUR']="0,0,0";
						$infFond['COULEURCONTOUR1']="0,0,0";
					}else if($this->XMLVer=="1.1"){//1.1
						$infFond['COULEURCONTOURHAUT']="0,0,0";
						$infFond['CONTOURHAUTVISIBLE']="false";
						$infFond['COULEURCONTOURDROITE']="0,0,0";
						$infFond['CONTOURDROITEVISIBLE']="false";
						$infFond['COULEURCONTOURBAS']="0,0,0";
						$infFond['CONTOURBASVISIBLE']="false";
						$infFond['COULEURCONTOURGAUCHE']="0,0,0";
						$infFond['CONTOURGAUCHEVISIBLE']="false";
					}
					$infFond['COULEURFOND']=$LigneArray[$indexLigneIs]['COULEURFOND'];
					$this->ajouterRectangle($infFond);
				}
				
				
				$realX=intval($propriete['X']);
				for($i=0;$i<count($ColonneArray);$i++){//parcour des colonnes pour afficher les valeurs
					$infTxt['TYPE']="TEXTE";
					$infTxt['X']=$realX;
					$infTxt['Y']=$this->RenduHautLigne;
					$infTxt['POLICE']=$LigneArray[$indexLigneIs]['NOMPOLICE'];
					$infTxt['TAILLE']=$LigneArray[$indexLigneIs]['TAILLEPOLICE'];
					
					/* AJOUT DU 2/09/2010 */
					/* Le nom de la colonne à changé de propriété avec le format 1.1 */
					$nomcolonne = $ColonneArray[$i]['NOMDELIGNE'];
					if($this->XMLVer=="1.1"){
						$nomcolonne = $ColonneArray[$i]['NOMDECOLONNE'];
					}
					/* FIN AJOUT DU 2/09/2010 */
					
					$infTxt['VALEUR']=sprintf("%s[%d][%s]"
															,$propriete['DONNEE']
															,$this->RenduLigneEncour
															,$nomcolonne); //$ColonneArray[$i]['DONNEE']
					
					$infTxt['COULEUR']=$LigneArray[$indexLigneIs]['COULEURPOLICE'];
					
					$infTxt['LARGEUR']=sprintf("%d",intval($ColonneArray[$i]['LARGEUR'])-(($ColonneArray[$i]['BORDDROIT']=="true")? (intval($ColonneArray[$i]['TAILLEBORDURE'])+1):1));
					
					$infTxt['HAUTEUR']=$LigneArray[$indexLigneIs]['HAUTEUR'];
					$infTxt['ALIGN']=$ColonneArray[$i]['ALIGNEMENTTEXTE'];
					
					$infTxt['BOLD']=($LigneArray[$indexLigneIs]['BOLD']=='true')? "true":"false";
					$infTxt['ITALIC']=($LigneArray[$indexLigneIs]['ITALIC']=='true')? "true":"false";
					$infTxt['UNDERLINE']=($LigneArray[$indexLigneIs]['UNDERLINE']=='true')? "true":"false";
					
					/*echo "<br>Infos TEXTE : <br><pre>";
					print_r($infTxt);
					echo "</pre>";*/
					
					$this->ajouterTexte($infTxt);
					//ajoute la largeur de la colonne à X pour changer de colonne
					$realX += intval($ColonneArray[$i]['LARGEUR']);
				}
				
				//bordure haute
				if($LigneArray[$indexLigneIs]['AFFICHERBORDHAUT']=="true"){ //il cert à rien car il n'est pas visible (caché par la bordure basse de la ligne supérieur si elle existe)
					$infLigne['TYPE']='TRAIT';
					$infLigne['X']=$propriete['X'];
					$infLigne['Y']=$this->RenduHautLigne;
					$infLigne['XFIN']=sprintf("%d",intval($propriete['X'])+intval($propriete['LARGEUR']));
					$infLigne['YFIN']=$this->RenduHautLigne;
					$infLigne['EPAISSEUR']=$LigneArray[$indexLigneIs]['EPAISSEURBORDHAUT'];
					$infLigne['COULEUR']=$LigneArray[$indexLigneIs]['COULEURBORDHAUT'];
					/*echo "<br>Infos du Trait Haut : <br><pre>";
					print_r($infLigne);
					echo "</pre>";*/
					$this->ajouterTrait($infLigne);
					//$infLigne['']=;
				}
				
				//bordure basse
				if($LigneArray[$indexLigneIs]['AFFICHERBORDBAS']=="true"){ //il cert à rien car il n'est pas visible
					$infLigne['TYPE']='TRAIT';
					$infLigne['X']=$propriete['X'];
					$infLigne['Y']=sprintf("%d",$this->RenduHautLigne+intval($LigneArray[$indexLigneIs]['HAUTEUR']));
					$infLigne['XFIN']=sprintf("%d",intval($propriete['X'])+intval($propriete['LARGEUR']));
					$infLigne['YFIN']=sprintf("%d",$this->RenduHautLigne+intval($LigneArray[$indexLigneIs]['HAUTEUR']));
					$infLigne['EPAISSEUR']=$LigneArray[$indexLigneIs]['EPAISSEURBORDBAS'];
					$infLigne['COULEUR']=$LigneArray[$indexLigneIs]['COULEURBORDBAS'];
					/*echo "<br>Infos du Trait Haut : <br><pre>";
					print_r($infLigne);
					echo "</pre>";*/
					$this->ajouterTrait($infLigne);
					//$infLigne['']=;
				}
				
				//calcule la valeur haute de la ligne suivante
				$this->RenduHautLigne += intval($LigneArray[$indexLigneIs]['HAUTEUR']);
				
			}
		
		}
		if($this->RenduLigneEncour==$max){
			$this->RepeatOther=false;
		}
		
		if($this->DEBUG) echo "<b>Affichage des bordure des colonnes</b><br>";
		
		//bordure des colonnes
		$realX=intval($propriete['X']);
		for($i=0;$i<count($ColonneArray)-1;$i++){//parcour des colonnes pour afficher les valeurs
			//ajoute la largeur de la colonne à X pour changer de colonne
			$realX += (intval($ColonneArray[$i]['LARGEUR']));
			if($ColonneArray[$i]['BORDDROIT']=="true"){
				$infLigne['TYPE']='TRAIT';
				$infLigne['X']=sprintf("%d",$realX-intval($ColonneArray[$i]['TAILLEBORDURE']));
				$infLigne['Y']=$propriete['Y'];
				$infLigne['XFIN']=sprintf("%d",$realX-intval($ColonneArray[$i]['TAILLEBORDURE']));
				$infLigne['YFIN']=sprintf("%d",intval($propriete['Y'])+intval($propriete['HAUTEUR']));
				$infLigne['EPAISSEUR']=$ColonneArray[$i]['TAILLEBORDURE'];
				$infLigne['COULEUR']=$ColonneArray[$i]['COULEURBORD'];
				$this->ajouterTrait($infLigne);
			
			}
			/*echo "<br>Infos TEXTE : <br><pre>";
			print_r($infTxt);
			echo "</pre>";*/
			
			
			
		}
		
		
		// Bordure du tableau
		
		if($this->DEBUG) echo "<b>Affichage des bordures du Tableau</b><br>";
		/* Ajout du 2/09/2010 */
		if($this->XMLVer=="1.0"){ // Fichier version 1.0
		/* FIN Ajout du 2/09/2010 */
			//bord haut
			if($propriete['BORDERTOP']=="true"){
				$infLigne['TYPE']='TRAIT';
				$infLigne['X']=$propriete['X'];
				$infLigne['Y']=$propriete['Y'];
				$infLigne['XFIN']=sprintf("%d",intval($propriete['X'])+intval($propriete['LARGEUR']));
				$infLigne['YFIN']=$propriete['Y'];
				$infLigne['EPAISSEUR']=$propriete['BORDERSIZE'];
				$infLigne['COULEUR']=$propriete['BORDERCOLOR'];
				/*echo "<br>Infos du Trait Haut : <br><pre>";
				print_r($infLigne);
				echo "</pre>";*/
				$this->ajouterTrait($infLigne);
				//$infLigne['']=;
			}
			
			//bord droit
			if($propriete['BORDERRIGHT']=="true"){
				$infLigne['TYPE']='TRAIT';
				$infLigne['X']=sprintf("%d",intval($propriete['X'])+intval($propriete['LARGEUR']));
				$infLigne['Y']=$propriete['Y'];
				$infLigne['XFIN']=sprintf("%d",intval($propriete['X'])+intval($propriete['LARGEUR']));
				$infLigne['YFIN']=sprintf("%d",intval($propriete['Y'])+intval($propriete['HAUTEUR']));
				$infLigne['EPAISSEUR']=$propriete['BORDERSIZE'];
				$infLigne['COULEUR']=$propriete['BORDERCOLOR'];
				/*echo "<br>Infos du Trait Droit : <br><pre>";
				print_r($infLigne);
				echo "</pre>";*/
				$this->ajouterTrait($infLigne);
				//$infLigne['']=;
			}
			
			//bord bas
			if($propriete['BORDERBOTTOM']=="true"){
				$infLigne['TYPE']='TRAIT';
				$infLigne['X']=$propriete['X'];
				$infLigne['Y']=sprintf("%d",intval($propriete['Y'])+intval($propriete['HAUTEUR']));
				$infLigne['XFIN']=sprintf("%d",intval($propriete['X'])+intval($propriete['LARGEUR']));
				$infLigne['YFIN']=sprintf("%d",intval($propriete['Y'])+intval($propriete['HAUTEUR']));
				$infLigne['EPAISSEUR']=$propriete['BORDERSIZE'];
				$infLigne['COULEUR']=$propriete['BORDERCOLOR'];
				/*echo "<br>Infos du Trait Bas : <br><pre>";
				print_r($infLigne);
				echo "</pre>";*/
				$this->ajouterTrait($infLigne);
				//$infLigne['']=;
			}
			
			//bord gauche
			if($propriete['BORDERLEFT']=="true"){
				$infLigne['TYPE']='TRAIT';
				$infLigne['X']=$propriete['X'];
				$infLigne['Y']=$propriete['Y'];
				$infLigne['XFIN']=$propriete['X'];
				$infLigne['YFIN']=sprintf("%d",intval($propriete['Y'])+intval($propriete['HAUTEUR']));
				$infLigne['EPAISSEUR']=$propriete['BORDERSIZE'];
				$infLigne['COULEUR']=$propriete['BORDERCOLOR'];
				/*echo "<br>Infos du Trait Gauche : <br><pre>";
				print_r($infLigne);
				echo "</pre>";*/
				$this->ajouterTrait($infLigne);
				//$infLigne['']=;
			}
		/* Ajout du 2/09/2010 */
		}else if($this->XMLVer=="1.1"){//1.1
			$infFond['TYPE']="RECTANGLE";
			$infFond['EXP']="TABLEAU_".$this->XMLVer;
			$infFond['X']=intval($propriete['X']);
			$infFond['Y']=intval($propriete['Y']);
			$infFond['LARGEUR']=$propriete['LARGEUR'];
			$infFond['HAUTEUR']=$propriete['HAUTEUR'];
			//désactive le contour
			$infFond['EPAISSEURCONTOUR']=$propriete['EPAISSEURCONTOUR'];
			$infFond['COULEURCONTOURHAUT']=$propriete['COULEURCONTOURHAUT'];
			$infFond['CONTOURHAUTVISIBLE']=$propriete['CONTOURHAUTVISIBLE'];
			$infFond['COULEURCONTOURDROITE']=$propriete['COULEURCONTOURDROITE'];
			$infFond['CONTOURDROITEVISIBLE']=$propriete['CONTOURDROITEVISIBLE'];
			$infFond['COULEURCONTOURBAS']=$propriete['COULEURCONTOURBAS'];
			$infFond['CONTOURBASVISIBLE']=$propriete['CONTOURBASVISIBLE'];
			$infFond['COULEURCONTOURGAUCHE']=$propriete['COULEURCONTOURGAUCHE'];
			$infFond['CONTOURGAUCHEVISIBLE']=$propriete['CONTOURGAUCHEVISIBLE'];
			
			$infFond['COULEURFOND']="-1";
			$this->ajouterRectangle($infFond);
			
		}
		/* FIN AJOUT DU 2/09/2010 */
		
		
		if($this->DEBUG) echo "<b>Fin du Tableau</b><br>";
		
	}
	
	private function ajoutNouvellePage(){
		$this->RenduHautLigne=0;
		$this->pdf->ezNewPage();
		/*$this->pdf->setColor(1,0.8,0.8);
		$this->pdf->selectFont('./pdfGenerator/fonts/Helvetica-Bold.afm');
		$texte="Version de Test !";
		$taille=50;
		//$texteH=$this->pdf->getTextHeight($taille,$texte);
		$texteW=$this->pdf->getTextWidth($taille,$texte);
		
		$cote = $texteW*0.7; //sqrt(pow($texteH,2) + pow($texteW,2));
		
		$this->pdf->addText(($this->pdf->ez['pageWidth']/2)-($cote/2),($this->pdf->ez['pageHeight']/2)-($cote/2),$taille,$texte,-45);*/
	
	}
	
	private function traitementCouleur($RVBColor){
		$es=explode(",",$RVBColor);
		if(count($es)==3){
			if(!(floatval($es[0])>=0.0 && floatval($es[0])<=1.0)) return "La valeur du Rouge n'est pas entre 0 et 1 !";
			if(!(floatval($es[1])>=0.0 && floatval($es[1])<=1.0)) return "La valeur du Bleu n'est pas entre 0 et 1 !";
			if(!(floatval($es[2])>=0.0 && floatval($es[2])<=1.0)) return "La valeur du Vert n'est pas entre 0 et 1 !";
		}else{
			return "La couleur ".$RVBcolor." n'est par au format 0,0,0 !";
		}
		
		return $es;
		
	}
	
	private function ajusterPourMargeX($x1){
		if($x1 < $this->pdfLargeurMin) $x1=$this->pdfLargeurMin;
		if($x1 > $this->pdfLargeurMax) $x1=$this->pdfLargeurMax;
		return $x1;
	}
	
	private function ajusterPourMargeY($y1){
		$y1=$this->pdfHauteurMax-$y1;
		if($y1 < $this->pdfHauteurMin) $y1=$this->pdfHauteurMin;
		if($y1 > $this->pdfHauteurMax) $y1=$this->pdfHauteurMax;
		return $y1;
	}
	
	private function ajouterErreur($txt){
		$this->Error=true;
		$this->ErrorMsg.=$txt."\n";
	
	
	}
	
	private function replaceVariableParData($text,$enVariable=false){
		//permet de remplacer les nom de variable par les données
		$e = explode("$",$text);
		if($e[0]!=$text){ //il y a au moins une variable
		
			$nbVariable = count($e); 
			for($i=1;$i<$nbVariable;$i++){
				if(substr($e[$i],0,5)=="data["){
					//retire le texte potentiel qui serai après
					$val = explode(" ",$e[$i]);
					$texteDesVariables[] = $val[0];
				}
			}
			
			if(count($texteDesVariables)>0){
				//crée les chemins
				for($i=0;$i<count($texteDesVariables);$i++){
					$path = explode("][",$texteDesVariables[$i]);
					$tmp=explode("[",$path[0]);
					$path[0]=$tmp[1];
					$tmp=explode("]",$path[count($path)-1]);
					$path[count($path)-1]=$tmp[0];
					$pathComplet[$i]=$path;
				}
				
				for($i=0;$i<count($texteDesVariables);$i++){
					//recherche de la valeur
					$path = $pathComplet[$i];
					if($path[0]=="data") $DataTmp=$this->CONFIG;
					else $DataTmp=$this->DATA;
					
					for($a=0;$a<count($path);$a++){
						/*echo "<br>Element path : ",$path[$a];
						echo "<br>Ancien datatmp : <br><pre>";
						print_r($DataTmp);
						echo "</pre>";*/
						$DataTmp = $DataTmp[$path[$a]];
						/*echo "<br>Nouveau datatmp : <br><pre>";
						print_r($DataTmp);
						echo "</pre>";*/
					}
					
					//echo "<br>Valeur : ",$DataTmp;
					$valeurDesVariables[$i]=$DataTmp;
					if($enVariable==true) return $DataTmp; //retourne la valeur trouvé et non le texte. Fonctionne que pour la première variable					
					//remplassement de la valeur
					if($this->DEBUG) echo "Remplace : '"."$".trim($texteDesVariables[$i])."' par '".$DataTmp."'<br>";
					$text = str_replace("$".trim($texteDesVariables[$i]),$DataTmp,$text);
				}
			}
			/*echo "<br>",$text,"<br><pre>";
			print_r($e);
			print_r($texteDesVariables);
			print_r($pathComplet);
			print_r($valeurDesVariables);
			echo "</pre>";*/
			
			
		}
		return $text;
	}
	
	private function triTableauSelonOrdre($tmpArray, $tmpOrder){
		/*echo "<br>Ordre Avant : <br><pre>";
		print_r($tmpOrder);
		echo "</pre>";
		echo "<br>Tableau Avant : <br><pre>";
		print_r($tmpArray);
		echo "</pre>";*/
		
		$i   = 0; /* Indice de répétition du tri */
        $j   = 0; /* Variable de boucle */
        $tmp = 0; /* Variable de stockage temporaire */
 		$tmpA = array();
        /* Booléen marquant l'arrêt du tri si le tableau est ordonné */
        $en_desordre = TRUE; 
        /* Boucle de répétition du tri et le test qui
           arrête le tri dès que le tableau est ordonné */
		$MAX = count($tmpOrder);
        for($i = 0 ; ($i < $MAX) && $en_desordre; $i++)
        {
                /* Supposons le tableau ordonné */
                $en_desordre = FALSE;
                /* Vérification des éléments des places j et j-1 */
                for($j = 1 ; $j < $MAX - $i ; $j++)
                {
                        /* Si les 2 éléments sont mal triés */
                        if($tmpOrder[$j] < $tmpOrder[$j-1])
                        {
                                /* Inversion des 2 éléments */
                                $tmp = $tmpOrder[$j-1];
                                $tmpOrder[$j-1] = $tmpOrder[$j];
                                $tmpOrder[$j] = $tmp;
 								
								$tmpA = $tmpArray[$j-1];
								$tmpArray[$j-1] = $tmpArray[$j];
								$tmpArray[$j] = $tmpA;
								
                                /* Le tableau n'est toujours pas trié */
                                $en_desordre = TRUE;
                        }
                }
        }
		/*echo "<br>Ordre Après : <br><pre>";
		print_r($tmpOrder);
		echo "</pre>";
		echo "<br>Tableau Après : <br><pre>";
		print_r($tmpArray);
		echo "</pre>";
		*/
		
		return $tmpArray;
	}
}



?>