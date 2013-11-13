<?php

class TAssetOF extends TObjetStd{
/*
 * Ordre de fabrication d'équipement
 * */
	
	function __construct() {
		$this->set_table(MAIN_DB_PREFIX.'assetOf');
    	$this->TChamps = array(); 	  
		$this->add_champs('numero,entity,fk_user','type=entier;');
		$this->add_champs('entity,temps_estime_fabrication,temps_reel_fabrication','type=float;');
		$this->add_champs('ordre','type=chaine;');
		$this->add_champs('date_besoin,date_lancement','type=date;');
		
		//clé étrangère : atelier
		parent::add_champs('fk_asset_workstation','type=entier;index;');
		
		parent::add_champs('fk_assetOf_parent','type=entier;index;');
		
	    $this->start();
		
		$this->TOrdre=array(
			'ASAP'=>'Au plut tôt'
			,'TODAY'=>'Dans la journée'
			,'TOMORROW'=> 'Demain'
			,'WEEK'=>'Dans la semaine'
			,'MONTH'=>'Dans le mois'
			
		);
		$this->TStatus=array(
			'DRAFT'=>'Brouillon'
			,'OPEN'=>'Lancé'
			,'CLOSE'=>'Terminé'
		);
		
		$this->workstation=null;
		
		$this->setChild('TAssetOF_line','fk_assetOf');
		$this->setChild('TAssetOF','fk_assetOf_parent');
		
	}
	
	function load(&$db, $id) {
		global $conf;
		
		$res = parent::load($db,$id);
		$this->loadWorkstation($db);
		
		return $res;
	}
	
	//Associe les équipements à l'OF
	function setEquipement($TAssetNeeded,$TAssetToMake){
		
		//Affectation des équipement pour les produit nécessaire
		foreach($this->TNeededProduct as $TNeededProduct){
			$TNeededProduct->setAsset($TAssetNeeded[$TNeededProduct->rowid]);
		}
		
		//Affectation des équipement pour les produit à créer
		foreach($this->TToMakeProduct as $ToMakeProduct){
			$ToMakeProduct->setAsset($TAssetToMake[$ToMakeProduct->rowid]);
		}
		
		return true;
	}
	
	function delLine(&$ATMdb,$iline){
		
		$this->TAssetOF_line[$iline]->to_delete=true;
		
	}
	
	//Ajout d'un produit TO_MAKE à l'OF
	function addProductComposition(&$ATMdb, $fk_product, $quantite_to_make=1){
			
		$Tab = $this->getProductComposition($ATMdb,$fk_product);
		
		foreach($Tab as $prod) {
			
			$this->addLine($ATMdb, $prod->fk_product, 'NEEDED', $prod->qty * $quantite_to_make);
			
		}
		
		return true;
	}
	
	//Retourne les produits NEEDED de l'OF concernant le produit $id_produit
	function getProductComposition(&$ATMdb,$id_product){
		global $db;	
		
		$Tab=array();
		$product = new Product($db);
		$product->fetch($id_product);
		$TRes = $product->getChildArbo($product->id);
		
		$this->getProductComposition_arrayMerge($ATMdb,$Tab, $TRes);
		
		return $Tab; 
	}
	
	private function getProductComposition_arrayMerge(&$ATMdb,&$Tab, $TRes, $qty_parent=1, $createOF=true) {
		
		foreach($TRes as $row) {
			
			$prod = new stdClass;
			$prod->fk_product = $row[0];
			$prod->qty = $row[1];
			
			if(isset($Tab[$prod->fk_product])) {
				$Tab[$prod->fk_product]->qty += $prod->qty * $qty_parent;
			}
			else {
				$Tab[$prod->fk_product]=$prod;	
			}
			
			if(!empty($row['childs'])) {
				
				if($createOF) {
					$this->createOFifneeded($ATMdb,$fk_product, $needed);
				}
				else {
					$this->getProductComposition_arrayMerge($Tab, $row['childs'], $prod->qty * $qty_parent);	
				}
				
				
			}
			
			
		}
		
	} 
	
	/*
	 * Crée une OF si produit composé pas en stock
	 */
	function createOFifneeded(&$ATMdb,$fk_product, $qty_needed) {
		
		$reste = $this->getProductStock($fk_product)-$qty_needed;
		
		if($reste>0) {
			null;
		}
		else {
			
			$k=$this->addChild('TAssetOF');
			$this->TAssetOF[$k]->addLine($ATMdb, $fk_product, 'TO_MAKE', abs($reste));
			
		}
		
	}
	/*
	 * retourne le stock restant du produit
	 */
	function getProductStock($fk_product) {
		
		return 0;//TODO
		
	}
	
	function createCommandeFournisseur($type='externe'){
		
		
		
		return $id_cmd_four;
	}
	
	function loadWorkstation(&$ATMdb){
		if(empty($this->workstation)) {
			
			$this->workstation=new TAssetWorkstation;
			$this->workstation->load($ATMdb, $this->fk_asset_workstation);
			
		}
	}
	
	//charge le produit TO_MAKE pour l'OF et ajoute une ligne correspondante s'il n'existe pas
	function loadToMakeProduct(&$ATMdb,$fk_product){
		global $db, $user;
		
		$TAssetOF_line = new TAssetOF_line;
		if($TAssetOF_line->load($fk_product)){
			$this->TToMakeProduct[] = $TAssetOF_line;
		}
		else {
			$this->addLine($fk_product,'TO_MAKE');		
		}
	}
	
	//Ajoute une ligne de produit à l'OF
	function addLine(&$ATMdb, $fk_product, $type, $quantite=1){
		global $user;
		
		$k = $this->addChild($ATMdb, 'TAssetOF_line');
		
		$TAssetOF_line = &$this->TAssetOF_line[$k];
		
		$TAssetOF_line->entity = $user->entity;
		$TAssetOF_line->fk_product = $fk_product;
		$TAssetOF_line->type = 'TO_MAKE';
		$TAssetOF_line->qty = $quantite;
		
		if($type=='TO_MAKE') {
			$this->addProductComposition($ATMdb,$fk_product,$type, $quantite);
		}
			
		
	}
	
}

class TAssetOF_line extends TObjetStd{
/*
 * Ligne d'Ordre de fabrication d'équipement 
 * */
	
	function __construct() {
		$this->set_table(MAIN_DB_PREFIX.'assetOf_line');
    	$this->TChamps = array(); 	  
		$this->add_champs('entity,fk_assetOf,fk_product,fk_asset','type=entier;');
		$this->add_champs('type','type=chaine;');
		
		//clé étrangère
		parent::add_champs('fk_assetOf_line_parent','type=entier;index;');
		
		$this->TType=array('NEEDED','TO_MAKE');
		
	    $this->start();
	}
	
	//Affecte l'équipement à la ligne de l'OF
	function setAsset($idAsset){
		
		$asset = new TAsset;
		$asset->load($ATMdb, $idAsset);
		$asset->status = 'indisponible';
		$asset->save($ATMdb);
		
		$this->fk_asset = $idAsset;
		$this->save($ATMdb);
		
		return true;
	}
	
	//Utilise l'équipement affecté à la ligne de l'OF
	function makeAsset(){
		
		$TAsset = new TAsset;
		$TAsset->load($ATMdb, $this->fk_asset);
		$TAsset->destock();
		
		return true;
	}
}

class TAssetWorkstation extends TObjetStd{
/*
 * Atelier de fabrication d'équipement
 * */
	
	function __construct() {
		$this->set_table(MAIN_DB_PREFIX.'asset_workstation');
    	$this->TChamps = array(); 	  
		$this->add_champs('entity','type=entier;');
		$this->add_champs('libelle','type=chaine;');
		
	    $this->start();
	}
	
	static function getWorstations($ATMdb) {
		$TWorkstation=array();
		$sql = "SELECT rowid, libelle FROM ".MAIN_DB_PREFIX."asset_workstation";
		$ATMdb->Execute($sql);
		while($ATMdb->Get_line()){
			$TWorkstation[$ATMdb->Get_field('rowid')]=$ATMdb->Get_field('libelle');
		}
		return $TWorkstation;
	}
	
}