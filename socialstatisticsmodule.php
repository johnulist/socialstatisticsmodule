<?php
if (!defined('_PS_VERSION_'))
    exit;

class SocialStatisticsModule extends Module
{
    public function __construct()
    {
        $this->name = 'socialstatisticsmodule';
        $this->tab = 'dashboard';
        $this->version = '1.0.0';
        $this->author = 'PrestaRock';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->bootstrap = true;
		
        parent::__construct();

        $this->displayName = $this->l('Social Statistics Module');
        $this->description = $this->l('Module gathers and shows G+ and FB likes count for every product on the dashboard.');

        $this->confirmUninstall = $this->l('Do you really want to uninstall this module?');	
    }

    public function install()
    {
        if (Shop::isFeatureActive())
            Shop::setContext(Shop::CONTEXT_ALL);

        if (!parent::install() ||
			!$this->registerHook('dashboardZoneTwo') ||
			!$this->installDb() ||
			!$this->updateSocialStatisticsModuleDB()
        )
			return false;            

        return true;
    }

    public function uninstall()
    {
        if (!parent::uninstall() ||
			!$this->uninstallDb()
        )
            return false;

        return true;
    }

	public function installDb()
	{
		
		return Db::getInstance()->execute('
			CREATE TABLE IF NOT EXISTS '._DB_PREFIX_.$this->name.' (
			`id_product` INTEGER(10) NOT NULL,
			`name` VARCHAR(64) DEFAULT NULL,
			`fb_like_count` INTEGER(10) NOT NULL,
			`google_plus_count` INTEGER(10) NOT NULL,
			PRIMARY KEY(`id_product`),
			INDEX (`id_product`))
			ENGINE='._MYSQL_ENGINE_.' default CHARSET=utf8'
		);
	}
	
	public function uninstallDb()
	{
		return Db::getInstance()->execute('DROP TABLE IF EXISTS '._DB_PREFIX_.$this->name);
	}
	
	public function displayList()
	{
		if ($result = $this->getData())
		{
			$this->fields_list = array(
				'id_product' => array(
					'title' => 'Id',
					'width' => '40',
					'type' => 'text'
					),
				'name' => array(
					'title' => 'Product',
					'width' => '250',
					'type' => 'text'
					),
				'fb_like_count' => array(
					'title' => 'FB Likes',
					'width' => '35',
					'type' => 'int'
					),
				'google_plus_count' => array(
					'title' => 'Google +1',
					'width' => '35',
					'type' => 'int'
					),
				);

			$helper = new HelperList();
			$helper->module = $this;
			$helper->title = $this->displayName;
			$helper->no_link = true;
			$helper->shopLinkType = '';
			$helper->show_toolbar = true;
			$helper->simple_header = false;
			$helper->identifier = 'id_product';
			$helper->actions= array ('view');
			$helper->toolbar_btn['database'] = array(
				'href' => AdminController::$currentIndex.'&refreshDB'.$this->name.
                        '&token='.Tools::getAdminTokenLite('AdminModules'),
				'desc' => $this->l('Refresh database')
			);
			$helper->listTotal = count($this->getData());
			$helper->table = $this->name;
			$helper->token = Tools::getAdminTokenLite('AdminModules');
			$helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
			return $helper->generateList($result, $this->fields_list);
		}
		return false;
	}
	
	public function updateSocialStatisticsModuleDB()
	{
		Db::getInstance()->delete(_DB_PREFIX_.$this->name, '');
		$productObj = new Product();
		$products = $productObj->getProducts(1, 0, 0, 'id_product', 'DESC' );
		
		$products_properties = $productObj->getProductsProperties(1,$products);
		
		foreach ($products_properties as $product) {
			
			Db::getInstance()->insert($this->name, array(
				'id_product' => (int)$product['id_product'],
				'name' => pSQL($product['name']),
				'fb_like_count' => (int)$this->getFacebookLikeCount($product['link']),
				'google_plus_count' => (int)$this->getGooglePlusOneCount($product['link']),
			));

			
		}
		return true;
	}
	public function getData(){
		
		$filter_id = Tools::getValue('filter_id');
		$query = 'SELECT `id_product`, `name`, `fb_like_count`, `google_plus_count` 
					FROM '._DB_PREFIX_.$this->name;

		return Db::getInstance()->ExecuteS($query);
	}
	
	public function getFacebookLikeCount($url){
		$data = json_decode(file_get_contents("http://api.facebook.com/method/fql.query?query=select%20like_count%20from%20link_stat%20where%20url='$url'&format=json"));
		
		return (int) $data[0]->like_count;
	}
	
	function getGooglePlusOneCount($url) {
		/* get source for custom +1 button */
		$contents = file_get_contents( 'https://plusone.google.com/_/+1/fastbutton?url=' .  $url );
		/* pull out count variable with regex */
		preg_match( '/window\.__SSR = {c: ([\d]+)/', $contents, $data );
		if( isset( $data[0] ) ) 
			return (int) str_replace( 'window.__SSR = {c: ', '', $data[0] );
		return 0;
	}
	
	public function hookDashboardZoneTwo($params)
	{
		if (Tools::isSubmit('viewsocialstatisticsmodule') && $id = Tools::getValue('id_product'))
			Tools::redirect($this->context->link->getProductLink($id));
		else if (Tools::isSubmit('refreshDBsocialstatisticsmodule')) {
			$this->updateSocialStatisticsModuleDB();
		}
		$this->context->smarty->assign(
            array(
				'ssm_form_list' => $this->displayList()
            )
        );
        return $this->display(__FILE__, 'socialstatisticsmodule.tpl');
	}
	
}