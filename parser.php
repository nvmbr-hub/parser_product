<?php 


function custom_file_download($url, $type = 'xml'){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
    /* Optional: Set headers...
    *    $headers = array();
    *    $headers[] = "Accept-Language: de";
    *    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    */
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
            exit('Error:' . curl_error($ch));
    }
    curl_close ($ch);
    $uploads = wp_upload_dir();
    $filename = $uploads['basedir'] . '/' . strtok(basename($url), "?") . '.' . $type;
    if (file_exists($filename)){
            @unlink($filename);
    }
    /* Optional: Change delimiters for CSV
    *
    *    $result = str_replace("!#", "|", $result);
    *    
    */
    file_put_contents($filename, $result);
    return str_replace($uploads['basedir'], $uploads['baseurl'], $filename);
}

add_filter( 'max_srcset_image_width', create_function( '', 'return 1;' ) );





remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20);
add_action('woocommerce_after_product_images_summary', 'woocommerce_template_single_excerpt', 10);

//   ИМПОРТ ПОСТАВЩИКОВ

if ( ! function_exists( 'woodmart_get_import_provider' ) ) {

	function woodmart_get_import_provider() {

		add_menu_page('Импорт поставщиков', 'Импорт поставщиков', 'manage_options', 'import_provider.php', 'import_provider','dashicons-groups', 200);
		
		add_submenu_page( 
			'',
			'Импорт на сайт',
			'Импорт на сайт',
			'manage_options',
			'import_process',
			'woodmart_import_process'
		);

		add_submenu_page( 
			'',
			'Импорт изображений',
			'Импорт изображений',
			'manage_options',
			'import_images',
			'woodmart_import_images'
		);

		add_submenu_page( 
			'',
			'Импорт остатков',
			'Импорт остатков',
			'manage_options',
			'import_stores',
			'woodmart_import_stores_amount'
		);

		add_submenu_page( 
			'',
			'Импорт фильтров',
			'Импорт фильтров',
			'manage_options',
			'import_filters',
			'woodmart_import_filters'
		);

		// Загрузка изображений в базу магазина
		function woodmart_import_images() {
			global $wpdb;

			$cid = $_GET['cid'];
			if(!empty($cid)){
				$provider_images = $wpdb->get_results( "SELECT pi.* FROM `{$wpdb->prefix}import_provider_product_images` pi INNER JOIN {$wpdb->prefix}import_provider_product_category pc ON pi.product_id = pc.provider_id 
					LEFT JOIN {$wpdb->prefix}import_provider_category_to_site cs ON pc.provider_id = cs.provider_id
					WHERE pi.post_id > 0 AND pc.category_id = '$cid' ORDER BY pi.post_id, pi.id ASC" );
			}else{
				$provider_images = $wpdb->get_results( "SELECT * FROM `{$wpdb->prefix}import_provider_product_images` WHERE post_id > 0 AND processed = 0 ORDER BY post_id" );
			}
//print_r($wpdb->last_query);
			$count_images = 0;

			$processed_images = array();
			$parent = '';
			$n = 1; // для проверки последнего элемента

			if(count($provider_images)){
				foreach($provider_images as $image){

					if(empty($parent)) {
						$parent = $image->post_id;
					}
if((int)$image->product_id == 53679) print_r($image->image.'<br>');
					if($parent !== $image->post_id || $n == count($provider_images)){
						set_post_thumbnail($parent, array_shift($processed_images));
						
						update_post_meta($parent, '_product_image_gallery', implode(',', $processed_images));
						$wpdb->query("UPDATE {$wpdb->prefix}import_provider_product_images SET processed = 1 WHERE post_id = '". $parent."'");

						$processed_images = array();
						$parent = $image->post_id;
					}
					/*$img = file_get_contents('https://17455_xmlexport:Q5ytqJkw@api2.gifts.ru/export/v2/catalogue/'.$image);
					file_put_contents('img.png', $img);*/
					/*$img_path = 'https://17455_xmlexport:Q5ytqJkw@api2.gifts.ru/export/v2/catalogue/' . $image->image;

					$img_id = media_sideload_image( urlencode($img_path, ), $image->post_id, 'Изображение' );*/
					
					if($image->provider == 1) $img_path = "https://17455_xmlexport:Q5ytqJkw@api2.gifts.ru/export/v2/catalogue/" . $image->image;
					elseif($image->provider == 2) $img_path = "https://www.oceangifts.ru" . $image->image;


					time_nanosleep(0, 300000000);
					$img_id = crb_insert_attachment_from_url($img_path, $image->post_id);
					if(!$img_id ){
						//echo $img_id->get_error_message();
						echo 'Ошибка '. $image->post_id.'<br>';
					}else{
						$processed_images[] = $img_id;
						$count_images++;
					}

					$n++;
				}
			}

			echo '<p>Загружено ' . $count_images . ' изображений.</p>';
		}

		function woodmart_import_process() {

			echo '<h1>Старт процесса загрузки товаров</h1>';

			echo '<button id="shop_import_start" class="btn btn-primary">Начать</button>';
			echo '<div id="import_content"></div>';
			echo "<script>
				jQuery(function($) {
					
					var categories, offset = 0, category_step = 0;
					//var import_step = 0;
					
					function start_import_step(import_step){						

						var requestData = {
					    	action: 'woodmart_import_process_start',
					    	step: import_step
					    };
					    
					    if(import_step == 0){
						    $.post('admin-ajax.php', requestData, function(response) {
						    	if(response) {
						    		categories = response.categories;
									console.log(categories);
						      		$('#import_content').html(response.text);
						      		import_step = import_step + 1;
						      		category_step = 0;

						      	if(response.start == 1) start_import_step(import_step);
						      }
						    });
						}
						else{

							//$.each(categories, function(index, category){


							    if(categories[category_step]){

									var requestData = {
								      action: 'woodmart_import_process_start',
								      category: categories[category_step],
								      step: import_step,
								      offset: offset
								    };

								    $.post('admin-ajax.php', requestData, function(response) {
								      if(response) {

								      	if(response.ajaxProductsCount){
								      		import_step = import_step + 1;
								      		offset = offset + 100;
								      	}else{
								      		category_step = category_step + 1;
								      		offset = 0;
								      	}


								      	$('#import_content').append(response.text);
								      	//$('#import_content').append('<p>'+response.query+'</p>');
								      	//$('#import_content').append('<p>'+response.groups+'</p>');
								      	console.log(response.groups);
								      	//

								      	start_import_step(import_step);
								      }
								    });
								}else{
									$('#import_content').append('<p>Загрузка завершена</p>');
								}
							//});

						}
					}
				    
					$('#shop_import_start').click(function(){
						
						start_import_step(0);


					});
				});
			</script>";
		}






		function import_provider() {
			global $wpdb;

			echo '<div class="myplugin ">';
				echo '<p class="submit automatic_login"><a class=" button button-primary type_auto enterb enter_automatic" id="start_import">Загрузка данных</a></p>';
				echo '<p classs="submit automatic_login"><a class=" button button-primary type_auto enterb enter_automatic" href="admin.php?page=manage_categories">Категории</a></p>';
				echo '<p classs="submit automatic_login"><a class=" button button-primary type_auto enterb enter_automatic" href="admin.php?page=manage_attr" id="get_import_attributes">Атрибуты</a></p>';
				echo '<p classs="submit automatic_login"><a class=" button button-primary type_auto enterb enter_automatic" href="admin.php?page=import_process">Импорт на сайт</a></p>';
				echo '<p classs="submit automatic_login"><a id="image_load_start" class=" button button-primary type_auto enterb enter_automatic" href="admin.php?page=import_images">Импорт изображений</a></p>';
				echo '<p classs="submit automatic_login"><a id="stores_load_start" class=" button button-primary type_auto enterb enter_automatic" href="admin.php?page=import_stores">Импорт остатков</a></p>';
				echo '<p classs="submit automatic_login"><a id="filters_load_start" class=" button button-primary type_auto enterb enter_automatic" href="admin.php?page=import_filters">Импорт фильтров</a></p>';


			//вывод категорий поставщиков для загрузки изображений
			$providers = $wpdb->get_results( $wpdb->prepare("SELECT * FROM `{$wpdb->prefix}import_providers`") );

			$categories = $wpdb->get_results( $wpdb->prepare("SELECT c.*, cs.site_category_id, t.name site_name, cs.clothes FROM `{$wpdb->prefix}import_provider_category` c LEFT JOIN `{$wpdb->prefix}import_provider_category_to_site` cs ON cs.provider_id = c.provider_id AND cs.provider = c.provider LEFT JOIN `{$wpdb->prefix}terms` t ON cs.site_category_id = t.term_id") );

			function get_provider_cat_tree($parent,$categories_data) {
			    $result = array();
			    foreach($categories_data as $category){
			        if ($parent == (string)$category->parent_id) {
			            $category->children = get_provider_cat_tree((string)$category->provider_id, $categories_data);
			            $result[] = $category;
			        }
			    }
			    return $result;
			}

			$categories = get_provider_cat_tree('1', $categories);

			echo '<select id="category">';
			echo '<option value=""></option>';
			foreach($categories as $category){
				echo '<option value="'.$category->provider_id.'">'.$category->name.'</option>';
				if(!empty($category->children)){
					foreach($category->children as $c){
						echo '<option value="'.$c->provider_id.'">&nbsp;&nbsp;&nbsp;&nbsp;'.$c->name.'</option>';
					}
				}
			}
			echo '</select>';

				echo '<div id="import_content"></div>';
			echo '</div>';

			echo "<script>
				jQuery(function($) {
				    
					$('#start_import').click(function(){
						var requestData = {
					      action: 'woodmart_start_import_provider_gift',
					    };
					    
					    $.post('admin-ajax.php', requestData, function(response) {
					      if(response) {
					      	$('#import_content').html(response);
					      }
					    });
					});


				    $('#category').change(function(){
				         $('#image_load_start').attr('href', 'admin.php?page=import_images&cid=' + $(this).val());
				         /*link_category = $('#options :+').val();
						 history.pushState({}, '', color);*/
				    });


				});
			</script>";
		}



	}


}
add_action('admin_menu', 'woodmart_get_import_provider');
add_action('admin_menu', 'woodmart_import_products_attr_manage');

		// Загрузка товаров в базу магазина
		function woodmart_import_process_start() {

			global $wpdb;
			
			/*if (!session_id())
			    
			else{
				
			}*/
session_start();
			$processed_products = $_SESSION['processed_products'];

			$loop = (int)$_POST['step'];
			$offset = (int)$_POST['offset'];

			//$offset = $offset;
			
			/*$continue = 1;
			
			if($loop > 10) $continue = 0;
			
			$output = array('text' => 'успешно '.$loop, 'start_loop' => $continue);
			//echo json_encode(array('text' => 'успешно '.$loop, 'start_loop' => $continue));
			*/

			if($loop == 0){

				$categories_to_site = $wpdb->get_results( "
					SELECT pc.id, pc.provider_id, pc.name, pc.parent_id, cs.site_category_id, pc.provider, cs.clothes FROM `{$wpdb->prefix}import_provider_category` pc 
					INNER JOIN `{$wpdb->prefix}import_provider_category_to_site` cs ON pc.provider_id = cs.provider_id AND pc.provider = cs.provider 
				" );
				

				if(!empty($categories_to_site) && count($categories_to_site)){
					
					$ajaxCategories = array();
					foreach($categories_to_site as $key => $value){
						$ajaxCategories[$key] = $value->provider_id;
					}
				}

				$product_groups = array();  //массив кросселс товаров, обновляет данные в группе товаров после основного цикла по товарам

				$output = array('text' => '<p>Загружаем данные из ' . count($categories_to_site) . ' категорий</p>', 'categories' => $ajaxCategories, 'start' => 1);
				
				wp_send_json( $output );
			
				die();
				
				

			}
			else{
				$ajaxCategory = $_POST['category'];

				$categories_to_site = $wpdb->get_results( "
					SELECT pc.id, pc.provider_id, pc.name, pc.parent_id, cs.site_category_id, pc.provider, cs.clothes FROM `{$wpdb->prefix}import_provider_category` pc 
					INNER JOIN `{$wpdb->prefix}import_provider_category_to_site` cs ON pc.provider_id = cs.provider_id AND pc.provider = cs.provider 
					WHERE pc.provider_id = '$ajaxCategory'
				" );

				$last_query = $wpdb->last_query;

				$_SESSION['import_provider_loop_category'] = $ajaxCategory; //category provider_id
			}

			//print_r( $wpdb->last_query.'<br>');
			// данные принадлежности товаров к категориям
			/*$product_to_categories = $wpdb->get_results( "SELECT * FROM `{$wpdb->prefix}import_provider_product_category` pc" );

			$product_to_categories_data = array();
			if(count($product_to_categories)){
				foreach($product_to_categories as $product){
					$product_to_categories_data[$product->provider_id.'|'.$product->provider][] = $product->category_id;
				}
			}*/


			$processed_products = $processed_products_category = $processed_products_post_id = array(); // массив уже загруженых товаров, чтобы не загружать те, которые в нескольких категориях
			//if($loop == 2)print_r($loop);
			if($loop > 1){
				$processed_products = $_SESSION['processed_products'];
				$product_groups = $_SESSION['product_groups'];
				//print_r($product_groups);print_r('<br><br>');
			}

			/*print_r('<br>сессия<br>');
			print_r($_SESSION['product_groups']);
			print_r('<br>');*/

			$pa_attributes_size = array('XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL', '4XL', '5XL');


			//массив только сопоставленных категорий, остальные пока пропускаем
			//if(count($categories_to_site)){

				//$category_load_count = 0;//удалить, это ограничитель счетчик загружаемых категорий

				foreach($categories_to_site as $c){

					$products_data = array();
					
					$products_query = "
						SELECT pp.*, pc.provider FROM `{$wpdb->prefix}import_provider_products` pp 
						INNER JOIN `{$wpdb->prefix}import_provider_product_category` pc ON pp.provider_id = pc.provider_id AND pp.provider = pc.provider 
						WHERE pc.category_id = '".$c->provider_id."' AND pp.main_product = 0 
						ORDER BY pp.id ASC
						LIMIT 100 OFFSET $offset
					";

					$products = $wpdb->get_results( $products_query );

					$ajaxProductsCount = $wpdb->num_rows;
//print_r($wpdb->last_query);
//print_r('<br><br>');
					$product_to_category = $products_data_relations = array();

/*SELECT r.*, tt.taxonomy, t.name  FROM `wp_term_relationships` r 
left join wp_term_taxonomy tt on r.term_taxonomy_id=tt.term_taxonomy_id
left join wp_terms t on tt.term_id=t.term_id
WHERE r.`object_id` = 64051283158*/
					if(count($products)) {

						$count = 0;
						
						$tags = '';
						$subtext = '';//для передачи сообщений в  ajax
						foreach($products as $product){


if($product->provider == 4 || $product->provider == 3) continue;
//if((int)$product->provider_id != 184143)  continue;
//if($product->provider_id !== "164681")  continue;
//if((int)$product->provider_id !== 78106)  {print_r($product);exit;}
							//проверка существования товара по артикулу
							$exists_product_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1", $product->sku ) );

							if($exists_product_id){
								$subtext .= '<p>Товар '.$product->name.' есть в базе. Артикул '.$product->sku.'</p>';
								//continue;
							}

							$pa_attributes = $processed_attributes = array();

							if(!in_array($product->provider_id.'|'.$product->provider, $processed_products) && !$exists_product_id){

								$processed_products_category[$product->provider_id.'|'.$product->provider][] = $c->site_category_id;
								

								$post_id = wp_insert_post( array(
									'post_title' 	=> wp_slash($product->name),
									'post_content' 	=> $product->content,
									'post_excerpt' 	=> $product->content,
									'post_status' 	=> 'publish',
									'post_type' 	=> "product",
									//'post_category' => $processed_products_category[$product->provider_id.'|'.$product->provider]
								) );

								if($product->product_group){
									$product_groups[(int)$product->provider][(int)$product->product_group][] = $post_id;//array('provider_id' => $product->provider_id, 'provider' => $product->provider, 'post_id' => $post_id);
								}

								foreach ($processed_products_category[$product->provider_id.'|'.$product->provider] as $to_category) {
									wp_set_object_terms($post_id, get_term($to_category)->slug, 'product_cat', true);
								}

								add_post_meta( $post_id, '_sku',  $product->sku);
								if($c->clothes) update_post_meta( $post_id, '_clothes',  1);

								// ищем вариации товара
								// $product_variations = $wpdb->get_results( "
								// 	SELECT pp.*, pc.provider FROM `{$wpdb->prefix}import_provider_products` pp 
								// 	INNER JOIN `{$wpdb->prefix}import_provider_product_category` pc ON pp.provider_id = pc.provider_id AND pp.provider = pc.provider 
								// 	WHERE pc.category_id = '".$c->provider_id."' AND pp.main_product = '".$product->provider_id."' ORDER BY pp.id ASC
								// " );
								$product_variations = $wpdb->get_results( "
									SELECT pp.*, s.amount store_amount, s.free store_free FROM `{$wpdb->prefix}import_provider_products` pp 
									LEFT JOIN `{$wpdb->prefix}import_provider_stores` s ON s.product_id = pp.provider_id
									WHERE pp.main_product = '".$product->provider_id."' AND pp.provider = '".$product->provider."' ORDER BY pp.id ASC
								" );
// print_r('<br>вариации  :<br>');								
// print_r($wpdb->last_query);
// print_r('<br><br>');
								
								if(count($product_variations)){

									wp_set_object_terms( $post_id, 'variable', 'product_type' );


									foreach($product_variations as $p_variation){

										//проверка вариаций по артикулу
										$exists_product_variation_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1", $p_variation->sku ) );

										if($exists_product_variation_id){
											$subtext .= '<p>Вариация '.$p_variation->name.' есть в базе. Артикул '.$p_variation->sku.'</p>';
											continue;
										}

										$variation_data = array();

										$product_attr = $wpdb->get_results( "SELECT * FROM `{$wpdb->prefix}import_provider_product_attr` pc WHERE product_id = '". $p_variation->provider_id."' AND provider = '".$p_variation->provider."'");
//print_r($wpdb->last_query);
										foreach($product_attr as $attr){
											switch($attr->name){
												case 'matherial':
												case 'material':
													$pa_attributes['pa_material'] = array(
																						'name' => 'pa_material',
																						'position' => 1,
																						'is_visible' => 1,
																						'is_variation' => 0,
																						'is_taxonomy' => 1
																					);

													$variation_data['attributes']['material'] = strip_tags((string)$attr->value);
													break;
												case 'product_size':
													$pa_attributes['pa_razmery'] = array(
																						'name' => 'pa_razmery',
																						'position' => 0,
																						'is_visible' => 1,
																						'is_variation' => 0,
																						'is_taxonomy' => 1
																					);

													$variation_data['attributes']['razmery'] = $attr->value;
													break;
												case 'size_code':
													$pa_attributes['pa_size'] = array(
																						'name' => 'pa_size',
																						'position' => 0,
																						'is_visible' => 1,
																						'is_variation' => 1,
																						'is_taxonomy' => 1
																					);

													$variation_data['attributes']['size'] = $attr->value;

													update_post_meta( $post_id, '_clothes',  1);

													break;
											case 'weight':
												$pa_attributes['pa_weight'] = array(
																					'name' => 'pa_weight',
																					'position' => 0,
																					'is_visible' => 1,
																					'is_variation' => 0,
																					'is_taxonomy' => 1
																				);
												$variation_data['attributes']['weight'] = $attr->value;
												break;
											case 'brand':
												$pa_attributes["pa_brend"] = array(
																					"name" => "pa_brend",
																					"position" => 0,
																					"is_visible" => 1,
																					"is_variation" => 0,
																					"is_taxonomy" => 1
																				);
												$brand = $wpdb->get_var( $wpdb->prepare( "SELECT term_id FROM $wpdb->terms WHERE name='%s' LIMIT 1", $attr->value ) );

												if(!empty($brand)) $variation_data['attributes']['brend'] = $brand;
												
												break;
											case 'pack':
												if(!empty($attr->value)){
													$pack = json_decode($attr->value);

													if(isset($pack->amount)){
														$pa_attributes['pa_pack_amount'] = array(
																							'name' => 'pa_pack_amount',
																							'position' => 0,
																							'is_visible' => 1,
																							'is_variation' => 0,
																							'is_taxonomy' => 0
																						);
														$variation_data['attributes']['pack_amount'] = $pack->amount;		
													}

													if(isset($pack->weight)){
														$pa_attributes['pa_pack_weight'] = array(
																							'name' => 'pa_pack_weight',
																							'position' => 0,
																							'is_visible' => 1,
																							'is_variation' => 0,
																							'is_taxonomy' => 0
																						);
														$variation_data['attributes']['pack_weight'] = $pack->weight;		
													}

													if(isset($pack->volume)){
														$pa_attributes['pa_pack_volume'] = array(
																							'name' => 'pa_pack_volume',
																							'position' => 0,
																							'is_visible' => 1,
																							'is_variation' => 0,
																							'is_taxonomy' => 0
																						);
														$variation_data['attributes']['pa_pack_volume'] = $pack->volume;		
													}
												}
												break;	
											}
										}

										$variation_data['post_title'] = $p_variation->name;
										$variation_data['sku'] = $p_variation->sku;
										$variation_data['sale_price'] = $p_variation->price;
										$variation_data['regular_price'] = $p_variation->price;
										$variation_data['stock_qty'] = $p_variation->store_amount;
										$variation_data['provider_id'] = $p_variation->provider_id;
										$variation_data['provider'] = $p_variation->provider;

										create_product_variation( $post_id, $variation_data );
									}

								} else {

									wp_set_object_terms( $post_id, 'simple', 'product_type' );


									$product_provider_stores_amount = $wpdb->get_results(  $wpdb->prepare("
										SELECT amount, free, inwayamount, inwayfree, code, provider FROM `{$wpdb->prefix}import_provider_stores`
										WHERE product_id = '%s' AND provider = '%d' ORDER BY id ASC", $product->provider_id, $product->provider )
									);

									if(count($product_provider_stores_amount)){
										$total_amount = 0;

										$product_stores_amount = array();//количество по разным складам

										foreach($product_provider_stores_amount as $key => $store_amount){
											$total_amount = $total_amount + $store_amount->free;

											$product_stores_amount[$key]['provider'] = $product->provider; // шаблон store + поставщик + номер склада
											$product_stores_amount[$key]['amount'] = $store_amount->amount;
											$product_stores_amount[$key]['free'] = $store_amount->free;
											$product_stores_amount[$key]['inwayamount'] = $store_amount->inwayamount;
											$product_stores_amount[$key]['inwayfree'] = $store_amount->inwayfree;
											$product_stores_amount[$key]['store_title'] = $store_amount->code;

										}

										update_post_meta( $post_id, '_stock_store_qty',  $product_stores_amount );
										update_post_meta( $post_id, '_manage_stock',  true );
										
									}

									if($product->provider == 1 || true) {
										add_post_meta( $post_id, '_price',  $product->price);
										add_post_meta( $post_id, '_stock_qty',  $total_amount);
										add_post_meta( $post_id, '_stock',  $total_amount);
									}
								}

								$print_attributes = array();
								// ищем атрибуты товара
								$product_attr = $wpdb->get_results( "SELECT * FROM `{$wpdb->prefix}import_provider_product_attr` pc WHERE product_id = '". $product->provider_id."' AND provider = '".$product->provider."'");

								foreach($product_attr as $attr){
									switch($attr->name){
										case 'matherial':
										case 'material':
											$pa_attributes["pa_material"] = array(
																				"name" => "pa_material",
																				"position" => 1,
																				"is_visible" => 1,
																				"is_variation" => 0,
																				"is_taxonomy" => 1
																			);

											//$attr->value = str_replace(',', ';', (string)$attr->value);
											

											$material = strip_tags((string)$attr->value);
											$tags .= $product->name;
											$tags .= '  --  ';
											$tags .= $material;

											if(term_exists( $material, 'pa_material' )) {
												wp_set_post_terms( $post_id, $material, 'pa_material', true );
												//$tags .= '  - exists <br>';
											}
											else {
												wp_insert_term( $material, 'pa_material' );
												wp_set_post_terms( $post_id, $material, 'pa_material', true );
												//$tags .= '  - insert term <br>';
											}

											break;
										case 'product_size':
										case 'size':
											$pa_attributes["pa_razmery"] = array(
																				"name" => "pa_razmery",
																				"position" => 0,
																				"is_visible" => 1,
																				"is_variation" => 0,
																				"is_taxonomy" => 1
																			);
											$attr->value = str_replace(',', '.', $attr->value);
											if(term_exists( $attr->value, 'pa_razmery' )) wp_set_post_terms( $post_id, $attr->value, 'pa_razmery', true );
											else {
												wp_insert_term( $attr->value, 'pa_razmery' );
												wp_set_post_terms( $post_id, $attr->value, 'pa_razmery', true );
											}
											break;
										case 'size_code':
											$pa_attributes["pa_size"] = array(
																				"name" => "pa_size",
																				"position" => 0,
																				"is_visible" => 1,
																				"is_variation" => 1,
																				"is_taxonomy" => 1
																			);
											$attr->value = str_replace(',', '.', $attr->value);
											if(term_exists( $attr->value, 'pa_size' )) wp_set_post_terms( $post_id, $attr->value, 'pa_size', true );
											else {
												wp_insert_term( $attr->value, 'pa_size' );
												wp_set_post_terms( $post_id, $attr->value, 'pa_size', true );
											}

											update_post_meta( $post_id, '_clothes',  1);
											
											break;
										case 'weight':

										/*if((int)$product->provider_id == 50888) {
	print_r($attr);
	print_r($attr->value);
	print_r('<br>');
	print_r((float)$attr->value);

}*/
											if(!is_numeric($attr->value)) {
												$pack_weight = json_decode($attr->value);
												$attr->value = $pack_weight[0];
											}else{
												$attr->value = (float)$attr->value;
											}

											$pa_attributes["pa_weight"] = array(
																				"name" => "pa_weight",
																				"position" => 0,
																				"is_visible" => 1,
																				"is_variation" => 0,
																				"is_taxonomy" => 1
																			);

											if(term_exists( $attr->value, 'pa_weight' )) wp_set_post_terms( $post_id, $attr->value, 'pa_weight', true );
											else {
												wp_insert_term( $attr->value, 'pa_weight' );
												wp_set_post_terms( $post_id, $attr->value, 'pa_weight', true );
											}
											break;
										case 'pack':
											if(!empty($attr->value)){
												$pack = json_decode($attr->value);
//print_r($pack);print_r('<br>');
												if(isset($pack->amount)){
													$pa_attributes['pa_pack_amount'] = array(
																						'name' => 'pa_pack_amount',
																						'position' => 0,
																						'is_visible' => 1,
																						'is_variation' => 0,
																						'is_taxonomy' => 1
																					);	

													if(term_exists( $pack->amount, 'pa_pack_amount' )) wp_set_post_terms( $post_id, $pack->amount, 'pa_pack_amount', true );
													else {
														wp_insert_term( $pack->amount, 'pa_pack_amount' );
														wp_set_post_terms( $post_id, $pack->amount, 'pa_pack_amount', true );
													}	
												}

												if(isset($pack->weight)){
													$pa_attributes['pa_pack_weight'] = array(
																						'name' => 'pa_pack_weight',
																						'position' => 0,
																						'is_visible' => 1,
																						'is_variation' => 0,
																						'is_taxonomy' => 1
																					);
													if(term_exists( $pack->weight, 'pa_pack_weight' )) wp_set_post_terms( $post_id, $pack->weight, 'pa_pack_weight', true );
													else {
														wp_insert_term( $pack->weight, 'pa_pack_weight' );
														wp_set_post_terms( $post_id, $pack->weight, 'pa_pack_weight', true );
													}	
												}

												if(isset($pack->volume)){
													$pa_attributes['pa_pack_volume'] = array(
																						'name' => 'pa_pack_volume',
																						'position' => 0,
																						'is_visible' => 1,
																						'is_variation' => 0,
																						'is_taxonomy' => 1
																					);
													if(term_exists( $pack->volume, 'pa_pack_volume' )) wp_set_post_terms( $post_id, $pack->volume, 'pa_pack_volume', true );
													else {
														wp_insert_term( $pack->volume, 'pa_pack_volume' );
														wp_set_post_terms( $post_id, $pack->volume, 'pa_pack_volume', true );
													}
												}
											}


											break;
										case 'brand':
											$pa_attributes["pa_brend"] = array(
																				"name" => "pa_brend",
																				"position" => 0,
																				"is_visible" => 1,
																				"is_variation" => 0,
																				"is_taxonomy" => 1
																			);

											if(term_exists( $attr->value, 'pa_brend' )) wp_set_post_terms( $post_id, $attr->value, 'pa_brend', true );
											else {
												wp_insert_term( $attr->value, 'pa_brend' );
												wp_set_post_terms( $post_id, $attr->value, 'pa_brend', true );
											}

											break;
										case 'print':
											$pa_attributes["pa_vid-naneseniya"] = array(
																				"name" => "pa_vid-naneseniya",
																				"position" => 0,
																				"is_visible" => 1,
																				"is_variation" => 0,
																				"is_taxonomy" => 1
																			);

											$print = json_decode($attr->value);
											$print = $print->name.' - '.$print->description;

											if(term_exists( $print, 'pa_vid-naneseniya' )) {
												wp_set_post_terms( $post_id, $print, 'pa_vid-naneseniya', true );
											}
											else {
												wp_insert_term( $print, 'pa_vid-naneseniya' );
												wp_set_post_terms( $post_id, $print, 'pa_vid-naneseniya', true );
											}

											break;	
									}

									$processed_attributes[] = $attr->name; //пока не используется
								}

								$color_code = '';
								if($product->provider == 1 || $product->provider == 2){
									
									$product_colors_query = "
										SELECT a.*, cg.title, cg.hex, ctg.group_id 
										FROM `wp_import_provider_product_attr` a
										inner join wp_import_provider_product_attr_color_to_group ctg on ctg.color_id = SUBSTRING_INDEX(a.value, '|', 1) and ctg.provider_id = a.provider
										inner join wp_import_provider_product_attr_color_group cg on cg.id =  ctg.group_id
										WHERE a.name = 'color' and a.product_id ='". $product->provider_id."'";

									$product_colors = $wpdb->get_results( $product_colors_query	);

									/*$product_colors = $wpdb->get_results( "
										SELECT pf.*, f.filtername, cg.color_id, cg.hex FROM `{$wpdb->prefix}import_provider_product_filters` pf 
										LEFT JOIN {$wpdb->prefix}import_provider_filters f ON pf.filtertypeid = f.filtertypeid AND pf.filterid = f.filterid
										LEFT JOIN {$wpdb->prefix}import_provider_product_attr_color_group cg ON cg.color_id = TO_BASE64(f.filtername)
										WHERE pf.provider_id = '". $product->provider_id."' AND pf.provider = '".$product->provider."' AND pf.filtertypeid = '21'");
if((int)$product->provider_id == 171251){print_r("<br>");
									print_r("
										SELECT pf.*, f.filtername, cg.color_id, cg.hex FROM `{$wpdb->prefix}import_provider_product_filters` pf 
										LEFT JOIN {$wpdb->prefix}import_provider_filters f ON pf.filtertypeid = f.filtertypeid AND pf.filterid = f.filterid
										LEFT JOIN {$wpdb->prefix}import_provider_product_attr_color_group cg ON cg.color_id = TO_BASE64(f.filtername)
										WHERE pf.provider_id = '". $product->provider_id."' AND pf.provider = '".$product->provider."' AND pf.filtertypeid = '21'"); }*/


								}elseif($product->provider == 2){
									$product_colors = array();
									/*$product_colors = $wpdb->get_results( "
										SELECT pf.*, f.filtername, cg.color_id, cg.hex FROM `{$wpdb->prefix}import_provider_product_filters` pf 
										LEFT JOIN {$wpdb->prefix}import_provider_filters f ON pf.filtertypeid = f.filtertypeid AND pf.filterid = f.filterid
										LEFT JOIN {$wpdb->prefix}import_provider_product_attr_color_group cg ON cg.color_id = TO_BASE64(f.filtername)
										WHERE pf.provider_id = '". $product->provider_id."' AND pf.provider = '".$product->provider."' AND pf.filtertypeid = '21'");*/
								}

								// выбор цветов товара по фильтру

								if(count($product_colors)){
									$pa_attributes["pa_cvet"] = array(
																		"name" => "pa_cvet",
																		"position" => 0,
																		"is_visible" => 1,
																		"is_variation" => 0,
																		"is_taxonomy" => 1
																	);

									//Загрузка цвета товара, выбор основного цвета между несколькими цветами
									if($product->provider == 1 || $product->provider == 2){
										if(count($product_colors) > 1){
											$color = $product_colors[1];

										}else{
											$color = $product_colors[0];
										}

										if(!empty($color->hex)){
											if(term_exists( $color->hex, 'pa_cvet' )) {

												wp_set_post_terms( $post_id, $color->hex, 'pa_cvet', true );
											}
											else {
												wp_insert_term( $color->hex, 'pa_cvet' );
												wp_set_post_terms( $post_id, $color->hex, 'pa_cvet', true );
											}
										}
										
									}

									/*foreach($product_colors as $color){

										if(!empty($color->hex)){
											if(term_exists( $color->hex, 'pa_cvet' )) {

												wp_set_post_terms( $post_id, $color->hex, 'pa_cvet', true );
											}
											else {
												wp_insert_term( $color->hex, 'pa_cvet' );
												wp_set_post_terms( $post_id, $color->hex, 'pa_cvet', true );
											}
										}

									}*/
								}

								add_post_meta( $post_id, '_product_attributes',  $pa_attributes);


								$count++;

								$processed_products[] = $product->provider_id.'|'.$product->provider;
								$processed_products_post_id[$product->provider_id.'|'.$product->provider] = $post_id; // массив новых созданных post_id

								//установка post_id для дальнейшей загрузки изображений
								$wpdb->query("UPDATE {$wpdb->prefix}import_provider_product_images SET post_id = ".$post_id." WHERE product_id = '". $product->provider_id."' AND provider = '".$product->provider."'");
							}else{

								$processed_products_category[$product->provider_id.'|'.$product->provider][] = $c->site_category_id;

								foreach ($processed_products_category[$product->provider_id.'|'.$product->provider] as $to_category) {
									wp_set_object_terms($post_id, get_term($to_category)->slug, 'product_cat', true);
								}
							}

							
							
							//if($count > 30 ) break;
							/*$post_name = sanitize_title($product->name);
							$products_data[] = $wpdb->prepare("(%d,%s,%s,%s,%d,%s,%s,%s,%s)", '', wp_slash($product->name),  '', 'publish', 1, 'product', 'open', 'open', $post_name );
							$products_data_relations[] = $wpdb->prepare("(%d,%s,%s,%s,%d,%s,%s,%s,%s)", '', wp_slash($product->name),  '', 'publish', 1, 'product', 'open', 'open', $post_name );*/

						} //END PRODUCTS FOREACH

						//print_r($product_groups[1][36550]);
						//добавление кросселов для каждого товара в группе
						if(count($product_groups)){
							foreach($product_groups as $product_group){
								foreach($product_group as $group_key => $group_data){
									if(count($group_data)){
										$group_count = count($group_data);
										/*if((int)$group_key == 36550) {

										}*/
										for($i = 0; $i <= $group_count-1; $i++){
											foreach($group_data as $group_post_key => $group_post_id){
												
												if($group_post_key == $i) {
													$group_data_mirror = $group_data;
													unset($group_data_mirror[$group_post_key]);

													
													//$crosssels = get_post_meta( $group_post_id, '_crosssell_ids');

													/*if(true && $i == 0 && (int)$group_key == 36550 && $loop == 3) {
														print_r('<br>Кросселы<br>');print_r($crosssels);print_r('<br><br>');
														print_r('<br>group_data_mirror<br>');print_r($group_data_mirror);print_r('<br><br>');
													}*/

													update_post_meta( $group_post_id, '_crosssell_ids',  $group_data_mirror);

													break;
												}
												
											}
										}
										//$product_groups[$product->product_group][] = $post_id;
									}
								}
							}
						}
						/*$query = "INSERT INTO {$wpdb->prefix}posts (id, post_title, post_content, post_status, post_author, post_type,'comment_status','ping_status', 'post_name') VALUES ";
						$query .= implode( ",\n", $products_data );

						$wpdb->query( $query );*/
					}

					$category_load_count++;

					//if($category_load_count > 2) break;
				}//END FOREACH CATEGORY

				$_SESSION['processed_products'] = $processed_products;
				$_SESSION['product_groups'] = $product_groups;


				$sitecategory = $wpdb->get_results( $wpdb->prepare("SELECT c.*, cs.site_category_id, t.name site_name, cs.clothes FROM `{$wpdb->prefix}import_provider_category` c LEFT JOIN `{$wpdb->prefix}import_provider_category_to_site` cs ON cs.provider_id = c.provider_id AND cs.provider = c.provider LEFT JOIN `{$wpdb->prefix}terms` t ON cs.site_category_id = t.term_id WHERE cs.site_category_id = '".$c->site_category_id."'") );

				$output = array('text' => '<p>Загрузили '.$ajaxProductsCount.' товаров в категорию ' . $sitecategory[0]->site_name.'</p>', 'categories' => '', 'start' => 2, 'query' => $last_query, 'ajaxProductsCount' => $ajaxProductsCount, 'subtext' => $subtext, 'groups' => $product_groups, 'tags' => $tags, 'pquery' => $products_query);

				wp_send_json( $output );

				die();
			//}

			//echo 'Загружено ' . $count . ' товаров.';

		}
add_action( 'wp_ajax_woodmart_import_process_start', 'woodmart_import_process_start' );

//Add product variation
function create_product_variation( $product_id, $variation_data ){

	global $wpdb;

    // Get the Variable product object (parent)
    $product = wc_get_product($product_id);


//print_r('var -');
//print_r($product_id);
    $variation_post = array(
        'post_title'  => $variation_data['post_title'],
        'post_name'   => 'product-'.$product_id.'-variation',
        'post_status' => 'publish',
        'post_parent' => $product_id,
        'post_type'   => 'product_variation',
        'guid'        => $product->get_permalink()
    );

    // Creating the product variation
    $variation_id = wp_insert_post( $variation_post );

    // Get an instance of the WC_Product_Variation object
    $variation = new WC_Product_Variation( $variation_id );

    // Iterating through the variations attributes
    foreach ($variation_data['attributes'] as $attribute => $term_name )
    {
        $taxonomy = 'pa_'.$attribute; // The attribute taxonomy

        // If taxonomy doesn't exists we create it (Thanks to Carl F. Corneil)
        if( ! taxonomy_exists( $taxonomy ) ){
            register_taxonomy(
            	$taxonomy, 
            	'product_variation', 
            	array( 'hierarchical' => false, 'label' => ucfirst( $attribute ), 'query_var' => true, 'rewrite' => array( 'slug' => sanitize_title($attribute)))
            );
        }

        // Check if the Term name exist and if not we create it.
        if( ! term_exists( $term_name, $taxonomy ) )
            wp_insert_term( $term_name, $taxonomy ); // Create the term

        $term_slug = get_term_by('name', $term_name, $taxonomy )->slug; // Get the term slug

        // Get the post Terms names from the parent variable product.
        $post_term_names =  wp_get_post_terms( $product_id, $taxonomy, array('fields' => 'names') );

        // Check if the post term exist and if not we set it in the parent variable product.
        if( ! in_array( $term_name, $post_term_names ) )
            wp_set_post_terms( $product_id, $term_name, $taxonomy, true );

        // Set/save the attribute data in the product variation
        update_post_meta( $variation_id, 'attribute_'.$taxonomy, $term_slug );
    }

    ## Set/save all other data

    // SKU    
    if( ! empty( $variation_data['sku'] ) )
        $variation->set_sku( $variation_data['sku'] );

    // Prices
    if( empty( $variation_data['sale_price'] ) ){
        $variation->set_price( $variation_data['regular_price'] );
    } else {
        $variation->set_price( $variation_data['sale_price'] );
        $variation->set_sale_price( $variation_data['sale_price'] );
    }
    $variation->set_regular_price( $variation_data['regular_price'] );

//if((int)$variation_data['provider_id'] == 184142){ print_r('stock_qty ' . $variation_data['stock_qty']);}
    // Stock
   // if( ! empty($variation_data['stock_qty']) ){
        $variation->set_manage_stock(true);
        $variation->set_stock_quantity( $variation_data['stock_qty'] );
        
        $variation->set_stock_status('');
    /*} else {
        $variation->set_manage_stock(false);
    }*/
    
    if($variation_data['weight'] > 0) $variation->set_weight($variation_data['weight']); // weight (reseting)
    else $variation->set_weight('');

    $variation->save(); // Save the data

    //добавляем количество по складам для вариаций
	$product_provider_stores_amount = $wpdb->get_results(  $wpdb->prepare("
		SELECT amount, free, inwayamount, inwayfree, code, provider FROM `{$wpdb->prefix}import_provider_stores`
		WHERE product_id = '%s' AND provider = '%d' ORDER BY id ASC", $variation_data['provider_id'], $variation_data['provider'] )
	);

	if(count($product_provider_stores_amount)){
		$total_amount = 0;

		$product_stores_amount = array();//количество по разным складам

		foreach($product_provider_stores_amount as $key => $store_amount){
			$total_amount = $total_amount + $store_amount->free;

			$product_stores_amount[$key]['provider'] = $store_amount->provider; // шаблон store + поставщик + номер склада
			$product_stores_amount[$key]['amount'] = $store_amount->amount;
			$product_stores_amount[$key]['free'] = $store_amount->free;
			$product_stores_amount[$key]['inwayamount'] = $store_amount->inwayamount;
			$product_stores_amount[$key]['inwayfree'] = $store_amount->inwayfree;
			$product_stores_amount[$key]['store_title'] = $store_amount->code;

		}

		update_post_meta( $variation_id, '_stock_store_qty',  $product_stores_amount );
		
	}
}




function woodmart_import_products_attr_manage() {
	add_submenu_page( 
		'',
		'Обработка атрибутов',
		'Обработка атрибутов',
		'manage_options',
		'manage_attr',
		'woodmart_manage_attributes'
	);
}

function woodmart_manage_attributes() {
	global $wpdb;
	echo '<h1>Обработка атрибутов</h1>';

	$provider = array(1 => 'gift.ru', 2 => 'oceangifts.ru');

	$actions = array('types', 'groups', 'manage');
	
	$action = $_GET['action'];
	
	if(empty($action) && !in_array($action, $actions)) $action = 'types';
	
	$active1 = $active2 = $active3 = '';
	switch($action){
		case 'types':
			$active1 = 'nav-tab-active';
			break;
		case 'groups':
			$active2 = 'nav-tab-active';
			break;
		case 'manage':
			$active3 = 'nav-tab-active';
			break;
	}
?>

	<h2 class="nav-tab-wrapper woo-nav-tab-wrapper">
        <a href="https://gift1.giftrank.ru/wp-admin/admin.php?page=manage_attr" class="nav-tab <?=$active1?>">Типы</a>
        <a href="https://gift1.giftrank.ru/wp-admin/admin.php?page=manage_attr&action=groups" class="nav-tab <?=$active2?>">Группы</a>
        <a href="https://gift1.giftrank.ru/wp-admin/admin.php?page=manage_attr&action=manage" class="nav-tab <?=$active3?>">Сопоставление</a>    
    </h2>
<?

	
	
	switch($action){
		case 'types':
			$types = $wpdb->get_results( "SELECT id, title, import_title FROM `{$wpdb->prefix}import_provider_product_attr_types`" );
			echo '<table id="datagrid" class="form-table"><tbody>';
			echo '<tr><th>№</th><th>Название</th><th>Обозначение</th><tr>';
			$i = 1;
			foreach($types as $type){
				echo '<tr><td>'.$i.'</td><td>'.$type->title.'</td><td>'.$type->import_title.'</td><tr>';
				$i++;
			}
			echo '</tbody></table>';
			break;

		case 'manage':
			$types = $wpdb->get_results( "SELECT id, title, import_title FROM `{$wpdb->prefix}import_provider_product_attr_types`" );
			$basis_colors = $wpdb->get_results( "SELECT id, title, hex FROM `{$wpdb->prefix}import_provider_product_attr_color_group`" );
		?>
			<div id="poststuff">
				<label for="attr-type">Выберите атрибут - 
					<select id="attr-type" name="attr-type">
							<?
							foreach($types as $type){
								//echo '<option value="'.$type->product_id.'-'.$attr->name.'">'.$attr->value.'</option>';
								echo '<option value="'.$type->import_title.'">'.$type->title.'</option>';
							}
							?>
					</select>
					<button id="get_attr_values" class="btn btn-primary">Показать</button>
				</label>

				<label id="attr-base-color-label" for="base-color" style="display:none">Основной цвет - 
					<select id="attr-base-color" name="attr-base-color">
							<?
							echo '<option value=""></option>';
							foreach($basis_colors as $basis_color){
								//echo '<option value="'.$type->product_id.'-'.$attr->name.'">'.$attr->value.'</option>';
								echo '<option value="'.$basis_color->id.'" data-hex="'.$basis_color->hex.'">'.$basis_color->title.'</option>';
							}
							?>
					</select>
					<div style="width:20px;height:20px;background:inherit;display:inline-block;border:1px solid #ddd;border-radius:50%;vertical-align: middle;" class="attr-base-color-hex"></div>
					<button id="set_attr_color_values" class="btn btn-primary">Сопоставить</button>
				</label>

				<label id="attr-color-label" for="attr-color" style="display:none">Добавить цвет - 
					<input type="text" id="new_attr_color" name="new_attr_color" value="" class="input-text">
					<input type="text" id="new_attr_color_hex" name="new_attr_color_hex" value="" placeholder="e6e6c5" class="input-text">
					<button id="new_attr_color_btn" class="btn btn-primary">Добавить</button>
				</label>
			</div>
			<div id="datagrid" class="message"></div>
			<div id="datagrid" class="content managed-attr-content"></div>
		<?
			break;
	}

	?>
	<script>
		jQuery(function($) {
		    
			$('#get_attr_values').click(function(){
				var requestData = {
			      action: 'woodmart_get_managed_attr',
			      attr_type: $('#attr-type').val()
			    };
			    
			    $.post('admin-ajax.php', requestData, function(response) {
			      if(response) {
			      	$('.managed-attr-content').html(response);
			      }
			    });
			});

			$('#new_attr_color_btn').click(function(){
				var requestData = {
			      action: 'woodmart_new_color_attr',
			      color: $('#new_attr_color').val(),
			      hex: $('#new_attr_color_hex').val()
			    };
			    
			    $.post('admin-ajax.php', requestData, function(response) {
			      if(response=="ok") {
			      	$('#new_attr_color').addClass('animation-rubber');
			      	setTimeout("jQuery('#new_attr_color, #new_attr_color_hex').val('');window.location.reload();", 1200);

			      }else{
			      	$('#new_attr_color_hex').css('background', '#ffc0cb');
			      }
			    });
			});

			$('#attr-type').change(function(){
				if($(this).val() == 'color'){
					$('#attr-color-label,#attr-base-color-label').show();
				}else $('#attr-color-label,#attr-base-color-label').hide();
			});

			$('#attr-base-color').change(function(){
				
				$('.attr-base-color-hex').css('background', '#' + $('#attr-base-color option').filter(":selected").data('hex'));

			});

			$('#set_attr_color_values').click(function(){
				var checked = [];
				$('.color_matching:checked').each(function(){
					checked.push($(this).val()+ '|' + $(this).data('provider'));
				});

				var requestData = {
			      action: 'woodmart_set_attr_color_values',
			      colors: checked,
			      group: $('#attr-base-color').val()
			    };
			    
			    $.post('admin-ajax.php', requestData, function(response) {
			      if(response) {
			      	$('.message').html(response);
			      	$('#get_attr_values').trigger('click');
			      }
			    });
			});

			$('body').on('click', '.delete_color', function(){

				//var el = $(this).parents('div').find('input');
				var el = $(this).prev('input');

				var requestData = {
			      action: 'woodmart_delete_attr_color_values',
			      color: el.val(),
			      provider: el.data('provider')
			    };
			    
			    $.post('admin-ajax.php', requestData, function(response) {
			      if(response) {
			      	$('.message').html(response);
			      	$('#get_attr_values').trigger('click');
			      	setTimeout("jQuery('.message').html('')", 5000);
			      }
			    });
			});
		});
	</script>
	<? wp_enqueue_style( 'woodmart', 'https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css', false, '4.1.1', 'all');?>
	<style>
	.animation-rubber{
		display: inline-block;
		margin: 0 0.5rem;

		animation: rubberBand; /* referring directly to the animation's @keyframe declaration */
		animation-duration: 1s; /* don't forget to set a duration! */
	</style>
	<?
}

//Устанавливает связь между категорией поставщика и категорией сайта
function woodmart_set_category_values() {
	global $wpdb;

	$categories = $_POST['categories'];
	$group = (int)$_POST['base_category'];
	$clothes = $_POST['clothes'];
	$category_data = array();

	if(count($categories) && !empty($group)){

		$category_to_group_query = array();
		foreach($categories as $cat){

			if(strpos($cat, '|') !== false) {
				$category_data = explode('|', $cat);
				$category_to_group_query[] = $wpdb->prepare("(%d,%s,%d,%d)", '', $category_data[0],  $category_data[1], $group);
			}

		}

		$query = "INSERT INTO {$wpdb->prefix}import_provider_category_to_site (id, provider_id, provider, site_category_id) VALUES ";
		$query .= implode( ",\n", $category_to_group_query );
		$query .= " ON DUPLICATE KEY UPDATE site_category_id = '$group'";

		$wpdb->query( $query );

		print 'Категория привязана к сайту';

	}elseif(empty($group)){

		foreach($categories as $cat){

			if(strpos($cat, '|') !== false) {
				$category_data = explode('|', $cat);
				$category_to_group_query[] = $wpdb->prepare("(provider_id = %s && provider = %d)", $category_data[0],  $category_data[1]);
			}

		}

		$query = "DELETE FROM {$wpdb->prefix}import_provider_category_to_site";
		$query .= " WHERE ".implode(' OR ', $category_to_group_query);

		$wpdb->query( $query );
		
		print 'Категории очищены';
	}


	if(count($clothes)){

		$cl_to_group_query = array();
		foreach($clothes as $cl){

			if(strpos($cl, '|') !== false) {
				$cl_data = explode('|', $cl);
				$cl_to_group_query[] = $wpdb->prepare("(provider_id = '%s' AND provider = %d)", $cl_data[0],  $cl_data[1]);
			}

		}

		$query = "UPDATE {$wpdb->prefix}import_provider_category_to_site SET clothes = 0 WHERE 1";
		$wpdb->query( $query );

		$query = "UPDATE {$wpdb->prefix}import_provider_category_to_site SET clothes = 1 WHERE ";
		$query .= implode( " OR ", $cl_to_group_query );
		$wpdb->query( $query );
	}


	die();
}
add_action( 'wp_ajax_woodmart_set_category_values', 'woodmart_set_category_values' );


function woodmart_get_managed_attr() {
	global $wpdb;

	$type = $_POST['attr_type'];
	/*if($type == 'color') $attributes = $wpdb->get_results( $wpdb->prepare("SELECT * FROM `wp_import_provider_product_attr` WHERE name = '%s' GROUP BY value", $type) );
	else */
		$attributes = $wpdb->get_results( $wpdb->prepare("SELECT * FROM `wp_import_provider_product_attr` WHERE name = '%s' GROUP BY value", $type) );

	if($type == 'color'){
		$processed_colors_data = $wpdb->get_results( $wpdb->prepare("SELECT cg.*, g.title FROM `wp_import_provider_product_attr_color_to_group` cg LEFT JOIN `wp_import_provider_product_attr_color_group` g ON cg.group_id = g.id ") );
		$processed_provider = $color_groups = $color_to_group = array();
		foreach($processed_colors_data as $processed){
			$processed_provider[$processed->provider_id][] = $processed->color_id;
			//Названия групп
			$color_groups[$processed->group_id] = $processed->title;
			//цвет к названию группы
			$color_to_group[$processed->color_id.$processed->provider_id] = $processed->title;
		}


	}


	echo '<h2>Не обработанные</h2>';

	$html_processed = array();
	foreach($attributes as $attr){
		
		$value = $attr;
		
		if($type == 'color') {
			$value = explode('|', $value->value);

			if(!in_array($value[0], $processed_provider[$attr->provider]))
				echo '<div class="row"><input type="checkbox" name="color_matching[]" class="color_matching" data-provider="'.$attr->provider.'" value="'.$value[0].'"/>'.$value[1].'</div>';
			else {
				//$html_group = $color_to_group[$value[0].$attr->provider];
				$html_processed[$color_to_group[$value[0].$attr->provider]][] = '<div class="row"><input type="checkbox" readonly name="color_processed[]" checked class="color_processed" data-provider="'.$attr->provider.'" value="'.$value[0].'"/>'.$value[1].
				'<span style="margin-left:30px;font-size:11px;cursor:pointer;" class="delete_color">Удалить</span>'.
				'</div>';
			}
		}
		else
			echo '<div class="row"><input type="checkbox" data-provider="'.$value->provider.'" value="'.$value->id.'"/>'.$value->value.'</div>';
		
	}

	echo '<h2>Обработанные</h2>';
	$check_group = array();
	foreach($html_processed as $group_title => $html){
		if(!in_array($group_title, $check_group)) {
			echo '<h3>'.$group_title.'</h3>';
			$check_group[] = $group_title;

			echo implode('', $html_processed[$group_title]);
		}

		
	}

	die();
}

add_action( 'wp_ajax_woodmart_get_managed_attr', 'woodmart_get_managed_attr' );

function woodmart_new_color_attr() {
	global $wpdb;

	$color = $_POST['color'];
	$hex = $_POST['hex'];
	if(!$hex) die('Установите цвет');


	$data = array(
	    'id' => '',
	    'title' => $color,
	    'hex' => $hex,
	    'color_id' => base64_encode(mb_strtolower(trim($color)))
	);

	$wpdb->insert(
	    "{$wpdb->prefix}import_provider_product_attr_color_group",
	    $data,
	    array( 
	        '%d',
	        '%s',
	        '%s',
	        '%s'
	    ) 
	);

	print 'ok';
	die();
}
add_action( 'wp_ajax_woodmart_new_color_attr', 'woodmart_new_color_attr' );



function woodmart_set_attr_color_values() {
	global $wpdb;

	$color_to_group_query = array();
	$colors = $_POST['colors'];
	$group = (int)$_POST['group'];

	if(count($colors)){
		foreach($colors as $c){

			if(strpos($c, '|') !== false) {
				$color_data = explode('|', $c);
				$color_to_group_query[] = $wpdb->prepare("(%d,%s,%d,%d)", '', $color_data[0], $group, $color_data[1]);
			}

		}

		$query = "INSERT INTO {$wpdb->prefix}import_provider_product_attr_color_to_group (id, color_id, group_id, provider_id) VALUES ";
		$query .= implode( ",\n", $color_to_group_query );
		$query .= " ON DUPLICATE KEY UPDATE group_id = '$group'";

		$wpdb->query( $query );
	}

	print 'Добавлена связь';

	die();
}
add_action( 'wp_ajax_woodmart_set_attr_color_values', 'woodmart_set_attr_color_values' );


function woodmart_delete_attr_color_values() {
	global $wpdb;

	$color = (int)$_POST['color'];
	$provider = (int)$_POST['provider'];

	$query = "DELETE FROM {$wpdb->prefix}import_provider_product_attr_color_to_group WHERE color_id = '$color' AND provider_id = '$provider'";

	$wpdb->query( $query );


	print 'Удалена связь';

	die();
}
add_action( 'wp_ajax_woodmart_delete_attr_color_values', 'woodmart_delete_attr_color_values' );


function woodmart_start_import_provider_gift() {
	global $wpdb;
	echo "<h2>Импорт товаров</h2><hr>";
	$table_name = "{$wpdb->prefix}import_provider_category";
	$url = "http://17455_xmlexport:Q5ytqJkw@api2.gifts.ru/export/v2/catalogue/tree.xml";
	if(($xml = simplexml_load_file($url)) !== FALSE){

//print_r($xml);exit;
		if(count($xml)){

			$wpdb->query("TRUNCATE ".$wpdb->prefix."import_provider_category");
			$wpdb->show_errors();
			$wpdb->query("TRUNCATE ".$wpdb->prefix."import_provider_product_category");
			$wpdb->show_errors();

			$category_count = $product_count = 0;

			$parentIDs = array();//массив отношения основных товаров к категориям, т.к. в загрузке нет вариаций. Для дальнейшей загрузки вариаций к основному товару и категории

			foreach($xml->page->page as $catalog){
				
				$children = $catalog->children();

				$name = (string)$catalog->name;
				
				$data = array(
				    'id' => '',
				    'provider_id' => (int) $catalog->page_id,
				    'parent_id' => (int) $catalog['parent_page_id'],
				    'name' => $name,
				    'provider' => 1
				);

				$wpdb->insert(
		            $table_name,
		            $data,
		            array( 
		                '%d',
		                '%d', 
		                '%d', 
		                '%s',
		                '%d'
		            ) 
		        );

				$wpdb->show_errors();

				$category_count++;

				if(count($children)){

					foreach($children as $child){
						if($child->getName() == 'product'){
							$data = array(
							    'id' => '',
							    'provider_id' => (int) $child->product,
							    'category_id' => (int) $child->page,
							    'provider' => 1
							);

							$wpdb->insert(
					            "{$wpdb->prefix}import_provider_product_category",
					            $data,
					            array( 
					                '%d',
					                '%d', 
					                '%d', 
					                '%d'
					            ) 
					        );

							$parentIDs[$child->product] = $child->page;

					        $product_count++;
						}
					}
				}

				//}			
				if(isset($catalog->page)) getCategoryData($catalog->page, $parentIDs);
				

			}
			
			echo 'Импорт tree завершен<br>';
			echo 'Загружено: ' . $category_count . ' категорий, '. $product_count . ' товаров.';
		}
	}



	//STOCK
	$url = "http://17455_xmlexport:Q5ytqJkw@api2.gifts.ru/export/v2/catalogue/stock.xml";

	if(($xml = simplexml_load_file($url)) !== FALSE){

		if(count($xml)){

			$products_amount = array();

			foreach($xml->stock as $stock){
//if((int)$stock->product_id == 3459 ) {print_r('<br>Количество  ');print_r($stock);print_r('<br><br>');	}
				//if((int)$stock->inwayfree !== 0 && $stock->inwayamount !== 0) {print_r('<br>Количество  ');print_r($stock);print_r('<br><br>');	exit;}
 				$products_amount[(int)$stock->product_id]['amount'] = (float)$stock->amount;
				$products_amount[(int)$stock->product_id]['free'] = (float)$stock->free;
				$products_amount[(int)$stock->product_id]['inwayamount'] = (float)$stock->inwayamount;
				$products_amount[(int)$stock->product_id]['inwayfree'] = (float)$stock->inwayfree;

			}
		}
	}

	$url = "http://17455_xmlexport:Q5ytqJkw@api2.gifts.ru/export/v2/catalogue/product.xml";
	
	if(($xml = simplexml_load_file($url)) !== FALSE){

		if(count($xml)){

			$wpdb->query("TRUNCATE ".$wpdb->prefix."import_provider_products");
			$wpdb->show_errors();

			$wpdb->query("TRUNCATE ".$wpdb->prefix."import_provider_product_attr");
			$wpdb->show_errors();

			$wpdb->query("TRUNCATE ".$wpdb->prefix."import_provider_stores");
			$wpdb->show_errors();

			$wpdb->query("TRUNCATE ".$wpdb->prefix."import_provider_product_filters");

			$product_count = $vary_product_count = 0;
			foreach($xml->product as $product){
/*print_r($product);
print_r('<br>');
if($product_count>2) break;*/
				//if((int)$product->product_id == 78106){print_r($product);exit;}
//if((int)$product->product_id ==184143) {print_r($product);print_r('<br><br>');}
				$children = $product->children();
				$attr = $attr_query = array();
				$not_attr = array('product_id', 'code', 'group', 'name', 'content', 'small_image', 'big_image', 'super_big_image', 'main_product', 'price', 'product_attachment', 'product');
				foreach($children as $cName => $cValue){
					if(!in_array($cName, $not_attr)) {
						if($cName == 'print'){
							$cValue = json_encode($cValue, JSON_UNESCAPED_UNICODE);
						}
						//if((int)$product->product_id ==30455 && $cName == 'pack') {print_r($cValue);print_r('<br><br>');}
						if(is_object($cValue) && ($cName != 'material' && $cName != 'matherial')) {

							$attr[$cName] = json_encode((array)$cValue, JSON_UNESCAPED_UNICODE);
							$cValue = json_encode((array)$cValue, JSON_UNESCAPED_UNICODE);

						}
						else {
							$attr[$cName] = (string)$cValue;
							$cValue = (string)$cValue;
						}

						
						$attr_query[] = $wpdb->prepare("(%d,%d,%s,%s,%d)", '', $product->product_id, $cName, $cValue, 1);
					}
				}

				$name = (string)$product->name;

				//if((int)$product->product_id ==127848) {print_r($product);exit;print_r('<br><br>');}

				//убираем ссылки
				/*$content = preg_replace("|<a.*?href=*.?>(.+?)</a>|is", '$1', $product->content);*/
				//$content = preg_replace("</a>", " ", $content);
	
				$data = array(
				    'id' => '',
				    'provider_id' => (int) $product->product_id,
				    'product_group' => isset($product->group) ? $product->group : '',
				    'sku' => $product->code,
				    'name' => $name,
				    'content' =>preg_replace("#<a[^>]*?>(.*?)<\/a>#is", "$1", $product->content),
				    'price' => $product->price->price,
				    'amount' => isset($products_amount[(int)$product->product_id]) ? $products_amount[(int)$product->product_id] : 0,
				    'provider' => 1,
				    'main_product' => 0,
				    'attr' => json_encode($attr, JSON_UNESCAPED_UNICODE)
				);

				$wpdb->insert(
		            "{$wpdb->prefix}import_provider_products",
		            $data,
		            array( 
		                '%d',
		                '%d', 
		                '%d', 
		                '%s',
		                '%s',
		                '%s',
		                '%f',
		                '%f',
		                '%d',
		                '%d',
		                '%s'
		            ) 
		        );

				//количество
				$data = array(
				    'id' => '',
				    'code' => '',
				    'product_id' => (int) $product->product_id,
				    'amount' => isset($products_amount[(int)$product->product_id]['amount']) ? $products_amount[(int)$product->product_id]['amount'] : 0,
				    'free' => isset($products_amount[(int)$product->product_id]['free']) ? $products_amount[(int)$product->product_id]['free'] : 0,
				    'inwayamount' => isset($products_amount[(int)$product->product_id]['inwayamount']) ? $products_amount[(int)$product->product_id]['inwayamount'] : 0,
				    'inwayfree' => isset($products_amount[(int)$product->product_id]['inwayfree']) ? $products_amount[(int)$product->product_id]['inwayfree'] : 0,
				    'provider' => 1
				);

				$wpdb->insert(
		            "{$wpdb->prefix}import_provider_stores",
		            $data,
		            array( 
		                '%d',
		                '%s', 
		                '%d', 
		                '%f',
		                '%f',
		                '%d'
		            ) 
		        );

				//атрибуты основного товара (не вариации)
				$query = "INSERT INTO {$wpdb->prefix}import_provider_product_attr (id, product_id, name, value, provider) VALUES ";
				$query .= implode( ",\n", $attr_query );

				$wpdb->query( $query );


				if(isset($product->product)){


					foreach($product->product as $subproduct){
//if((int)$product->product_id ==30455) {print_r($subproduct);print_r('<br><br>');}
						$subproduct_children = $subproduct->children();
						$attr = array();
						$not_attr = array('product_id', 'code', 'group', 'name', 'content', 'small_image', 'big_image', 'super_big_image', 'main_product', 'price', 'product_attachment', 'product');
						
						$attr_query = array();
						foreach($subproduct_children as $cName => $cValue){
							//if(!in_array($cName, $not_attr)) $attr[$cName] = (string)$cValue;

							if(!in_array($cName, $not_attr)) $attr_query[] = $wpdb->prepare("(%d,%d,%s,%s,%d)", '', $subproduct->product_id, $cName, $cValue, 1);
						}

						$data = array(
						    'id' => '',
						    'provider_id' => (int) $subproduct->product_id,
						    'product_group' => isset($subproduct->group) ? $subproduct->group : '',
						    'sku' => $subproduct->code,
						    'name' => $subproduct->name,
						    'price' => $subproduct->price->price,
						    'amount' => isset($products_amount[(int)$subproduct->product_id]) ? $products_amount[(int)$subproduct->product_id] : 0,
						    'provider' => 1,
						    'main_product' => $subproduct->main_product,
						    'attr' => json_encode($attr, JSON_UNESCAPED_UNICODE)
						);

						$wpdb->insert(
				            "{$wpdb->prefix}import_provider_products",
				            $data,
				            array( 
				                '%d',
				                '%d', 
				                '%d', 
				                '%s',
				                '%s',
				                '%f',
				                '%f',
				                '%d',
				                '%d',
				                '%s'
				            ) 
				        );

						$query = "INSERT INTO {$wpdb->prefix}import_provider_product_attr (id, product_id, name, value, provider) VALUES ";
						$query .= implode( ",\n", $attr_query );

						$wpdb->query( $query );

						//добаляем вариацию в категорию
						if($subproduct->main_product){
							//print_r($subproduct->product_id);print_r('<br><br>');
							$data_cat = array(
							    'id' => '',
							    'provider_id' => (int) $subproduct->product_id,
							    'category_id' => (int) $parentIDs[(int)$subproduct->main_product],
							    'provider' => 1
							);

							$wpdb->insert(
					            "{$wpdb->prefix}import_provider_product_category",
					            $data_cat,
					            array( 
					                '%d',
					                '%d', 
					                '%d', 
					                '%d'
					            ) 
					        );
//if((int)$subproduct->product_id == 164691) {print_r('<br>Количество  вариации');print_r($products_amount[(int)$subproduct->product_id]);	}
							//количество
							$data = array(
							    'id' => '',
							    'code' => '',
							    'product_id' => (int) $subproduct->product_id,
							    'amount' => isset($products_amount[(int)$subproduct->product_id]['amount']) ? $products_amount[(int)$subproduct->product_id]['amount'] : 0,
							    'free' => isset($products_amount[(int)$subproduct->product_id]['free']) ? $products_amount[(int)$subproduct->product_id]['free'] : 0,
							    'inwayamount' => isset($products_amount[(int)$subproduct->product_id]['inwayamount']) ? $products_amount[(int)$subproduct->product_id]['inwayamount'] : 0,
							    'inwayfree' => isset($products_amount[(int)$subproduct->product_id]['inwayfree']) ? $products_amount[(int)$subproduct->product_id]['inwayfree'] : 0,
							    'provider' => 1
							);

							$wpdb->insert(
					            "{$wpdb->prefix}import_provider_stores",
					            $data,
					            array( 
					                '%d',
					                '%s', 
					                '%d', 
					                '%f',
					                '%f',
					                '%d'
					            ) 
					        );
						}

				        $vary_product_count++;
					}
				}
				$wpdb->show_errors();


				//Загрузка изображений
				if(count($product->product_attachment)){
					
					$img_query = array();

					//$product_images = $wpdb->get_col( $wpdb->prepare("SELECT image FROM `wp_import_provider_product_images` WHERE product_id = %d AND provider = 1", $product->product_id) );

					$image_load = false;
					foreach($product->product_attachment as $attachment){
						$img_query[] = $wpdb->prepare("(%d,%s,%d,%d)", '', $attachment->image, $product->product_id, 1);
						
						if(!in_array($attachment->image, $product_images)) {
							$image_load = true;
							//print_r('!in_array ' .$attachment->image.'<br>');
						}
					}

					if($image_load || true){

						$query = $wpdb->prepare("DELETE FROM {$wpdb->prefix}import_provider_product_images WHERE product_id = '%s' AND provider = 1", $product->product_id);

						$wpdb->query( $query );

						$query = "INSERT INTO {$wpdb->prefix}import_provider_product_images (id, image, product_id, provider) VALUES ";
						$query .= implode( ",\n", $img_query );
						$wpdb->query( $query );
					}
				}

				$product_filter_query = $filter_color_in = array();
				//$product_attr_query = array();

				if(count($product->filters)){
					foreach($product->filters->filter as $filter){

						
						$product_filter_query[] = $wpdb->prepare("('', %s, %d, %d, 1)", $product->product_id, $filter->filtertypeid, $filter->filterid);
						if($filter->filtertypeid == 21) $filter_color_in[] = (int)$filter->filterid;
					}
					//if((int)$product->product_id == 171251) {print_r($filter_color_in);exit;}
				}

				//Загрузка цветов в атрибуты товара
				if(count($filter_color_in)){
					$filter_color_in = implode(',', $filter_color_in);

					$colorName = $wpdb->get_results("SELECT filtername FROM {$wpdb->prefix}import_provider_filters WHERE filtertypeid = 21 AND filterid IN ($filter_color_in)");

					if(!empty($colorName )){
						$colorName_query = array();
						foreach($colorName as $c){
							$colorName_query[] = $wpdb->prepare("('', %s, %s, %s, 1)", $product->product_id, 'color', base64_encode($c->filtername).'|'.$c->filtername);
							
						}
						
						$query = "INSERT INTO {$wpdb->prefix}import_provider_product_attr (id, product_id, name, value, provider) VALUES ";
						$query .= implode( ",\n", $colorName_query );
						$wpdb->query( $query );
					}
				}

				if(count($product_filter_query)){
					$query = "INSERT INTO {$wpdb->prefix}import_provider_product_filters (id, provider_id, filtertypeid, filterid, provider) VALUES ";
					$query .= implode( ",\n", $product_filter_query );
					$wpdb->query( $query );
				}

				$product_count++;
			}//end product foreach
		}
	}
	echo '<p>Импорт product завершен</p>';
	echo 'Загружено: ' . ($product_count+$vary_product_count) . ' описаний товаров, из них '. $vary_product_count. ' вариативных.';

	echo '<p>Импорт поставщика gifts.ru завершен</p>';
	echo '<p>Начат импорт поставщика oceangifts.ru</p><div id="import_message"></div>';

	echo "<script>
		jQuery(function($) {
		    var requestData = {
		      action: 'woodmart_get_import_provider_catalog',
		      dataType: 'json'
		    };

		    $.post('admin-ajax.php', requestData, function(response) {
		      if(response.status == 1) {
		      	response.message = response.message + '<p>Начат импорт поставщика happygifts.ru</p><div id=\"import_message_4\"></div>'
		      	$('#import_message').html(response.message);


				    var requestData = {
				      action: 'woodmart_get_import_provider_happygifts',
				      dataType: 'json'
				    };

				    $.post('admin-ajax.php', requestData, function(response) {
				      if(response) {
				      	$('#import_message_4').html(response);
				      }
				    });

		      }
		    });
		});
	</script>";
}


function getCategoryData($parent, &$parentIDs){
	global $wpdb;
	//print_r('<br>');
	$table_name = "{$wpdb->prefix}import_provider_category";
	foreach($parent as $category){
		
		$children = $category->children();

		$data = array(
		    'id' => '',
		    'provider_id' => (int) $category->page_id,
		    'parent_id' => (int) $category['parent_page_id'],
		    'name' => (string)$category->name,
		    'provider' => 1
		);
		$wpdb->insert(
            $table_name,
            $data,
            array( 
                '%d',
                '%d', 
                '%d', 
                '%s'
            ) 
        );
        $wpdb->show_errors();
	

		if(count($children)){

			foreach($children as $child){

				if($child->getName() == 'product'){
					//print_r($child->product.'-'.$child->page.'<br>');
					$data = array(
					    'id' => '',
					    'provider_id' => (int) $child->product,
					    'category_id' => (int) $child->page,
					    'provider' => 1
					);

					$wpdb->insert(
			            "{$wpdb->prefix}import_provider_product_category",
			            $data,
			            array( 
			                '%d',
			                '%d', 
			                '%d', 
			                '%d'
			            ) 
			        );

					$k = (int)$child->product;
			        $parentIDs[$k] = (int)$child->page;
				}
			}
		}

		if(isset($category->page)) getCategoryData($category->page, $parentIDs);//$child = $category->page->children();

	}
}
add_action( 'wp_ajax_woodmart_start_import_provider_gift', 'woodmart_start_import_provider_gift' );
add_action( 'wp_ajax_nopriv_woodmart_start_import_provider_gift', 'woodmart_start_import_provider_gift' );

//   OCEANGIFTS.RU

if ( ! function_exists( 'woodmart_get_import_provider_catalog' ) ) {

	function woodmart_get_import_provider_catalog() {
		
		global $wpdb;

		$output = array();

		$table_name = "{$wpdb->prefix}import_provider_category";

		$url = "http://www.oceangifts.ru/upload/catalog.json";

		if(($json = file_get_contents($url)) !== FALSE){


			$json = json_decode($json);

			$category_count = 0;

			foreach($json->categories as $category){

				$name = (string)$category->name;
				
				$data = array(
				    'id' => '',
				    'provider_id' => (int) $category->id,
				    'parent_id' => 1,
				    'name' => $name,
				    'provider' => 2
				);
				
				$wpdb->insert(
		            $table_name,
		            $data,
		            array( 
		                '%d',
		                '%d', 
		                '%d', 
		                '%s',
		                '%d'
		            ) 
		        );

				$wpdb->show_errors();

				$category_count++;

				if(isset($category->subcategories) && !empty($category->subcategories)) getCategoryDataOceanProvider($category->id, $category->subcategories);
			}

			//$wpdb->query("TRUNCATE ".$wpdb->prefix."import_provider_stores");
			//$wpdb->query("TRUNCATE ".$wpdb->prefix."import_provider_product_attr");
			$i=0;
			
			$product_to_category_data = $placeholders = array();
			foreach($json->products as $product){
				$i++;
				$name = (string)$product->name;


				//Нанесение выбираем сразу, т.к. одинаков для всех товаров группы
				//$works - массив с видами нанесений
				$works = array();
				if(isset($product->plottings) && count($product->plottings)) {
					foreach($product->plottings as $plotting){
						if(count($plotting->works)){
							foreach($plotting->works as $work){
								if(!in_array($work, $works)) {
									$works[] = $work;
								}
							}
						}
					}					
				}

				$attr = array();
				if(count($product->colors)){
					foreach($product->colors as $color){

						//Упаковка
						$box_count = 0;

						if(isset($color->boxing_info)) {

							//print_r($color->boxing_info);
							//foreach($color->boxing_info as $boxing_info){

									if(isset($color->boxing_info[1]) && isset($color->boxing_info[1]->table)) {
//print_r($boxing_info[1]->table);
										foreach($color->boxing_info[1]->table as $box){
											if($box->name == 'Кол-во в упаковке'){
												$box_count = (string)$box->value;
											}
										} 
									}

							//}


						}

						
						//Изображения
						$img_query = array();
						if(isset($color->photos) && count($color->photos)) {
							
							if(isset($color->sizes[0]->id)) {

								//print_r($color->sizes[0]['id'].'<br>');
								$query = $wpdb->prepare("DELETE FROM {$wpdb->prefix}import_provider_product_images WHERE product_id = '%s' AND provider = 2", $color->sizes[0]->id);

								$wpdb->query( $query );

								foreach($color->photos as $photo)
									$img_query[] = $wpdb->prepare("(%d,%s,%s,%d)", '', $photo, $color->sizes[0]->id, 2);


								$query = "INSERT INTO {$wpdb->prefix}import_provider_product_images (id, image, product_id, provider) VALUES ";
								$query .= implode( ",\n", $img_query );
								$wpdb->query( $query );
							}

						}


						if(count($color->sizes)){
							foreach($color->sizes as $key => $size){
								
								//Вариативный товар
								if(isset($size->size) && !empty($size->size)){
									
									//добавляем первый основной товар и первую вариацию
									if($key == 0){

										$data = array(
										    'id' => '',
										    'provider_id' => (int) $size->id,
										    'product_group' => isset($product->main_id) ? $product->main_id : 0,
										    'sku' => str_replace($size->size, '', $size->article),
										    'name' => $name,
										    'content' => preg_replace("#<a[^>]*?>(.*?)<\/a>#is", "$1", $product->info),
										    'price' => $size->price,
										    //'amount' => isset($products_amount[(int)$product->product_id]) ? $products_amount[(int)$product->product_id] : 0,
										    'provider' => 2,
										    'main_product' => '0',
										    //'attr' => json_encode($attr, JSON_UNESCAPED_UNICODE)
										);

										$wpdb->insert(
								            "{$wpdb->prefix}import_provider_products",
								            $data,
								            array( 
								                '%d',
								                '%s', 
								                '%d', 
								                '%s',
								                '%s',
								                '%s',
								                '%f',
								                '%d',
								                '%s'
								            ) 
								        );

										//Атрибуты основного товара
										if(!empty($product->brand)) 	$attr[]	= $wpdb->prepare("(%d,%d,%s,%s,%d)", '', $size->id, 'brand', $product->brand, 2);
										if(!empty($product->material)) 	$attr[] = $wpdb->prepare("(%d,%d,%s,%s,%d)", '', $size->id, 'material', $product->material, 2);
										if(!empty($product->volume)) 	$attr[] = $wpdb->prepare("(%d,%d,%s,%s,%d)", '', $size->id, 'volume', $product->volume, 2);
										if(!empty($product->memory)) 	$attr[] = $wpdb->prepare("(%d,%d,%s,%s,%d)", '', $size->id, 'memory', $product->memory, 2);
										if(!empty($product->cover)) 	$attr[] = $wpdb->prepare("(%d,%d,%s,%s,%d)", '', $size->id, 'cover', $product->cover, 2);
										if(!empty($product->format)) 	$attr[] = $wpdb->prepare("(%d,%d,%s,%s,%d)", '', $size->id, 'format', $product->format, 2);
										if(!empty($product->weight)) 	$attr[] = $wpdb->prepare("(%d,%d,%s,%s,%d)", '', $size->id, 'weight', $product->weight, 2);
										//if(!empty($product->size)) 		$attr[] = $wpdb->prepare("(%d,%d,%s,%s,%d)", '', $size->id, 'size_code', $product->size, 2);
										
										//цвет
										if(!empty($color->color->name)) $attr[]= $wpdb->prepare("(%d,%d,%s,%s,%d)", '', $size->id, 'color', $color->color->id.'|'.$color->color->name, 2);
										
										//Количество в упаковке
										if($box_count) $attr[]= $wpdb->prepare("(%d,%d,%s,%s,%d)", '', $size->id, 'pack', json_encode(array('amount' => $box_count), JSON_UNESCAPED_UNICODE), 2);

										//добавляем нанесение
										if(!empty($works)) {
											foreach($works as $work){
												$parts = explode(':', $work);
												$work = json_encode(array('name' => $parts[0], 'description' => $parts[1]), JSON_UNESCAPED_UNICODE);
												$attr[]= $wpdb->prepare("(%d,%d,%s,%s,%d)", '', $size->id, 'print', $work, 2);
											}
										}

										$data = array(
										    'id' => '',
										    'provider_id' => (string)(((int) $size->id) * 10),
										    'product_group' => '0',
										    'sku' => $size->article,
										    'name' => $name,
										    'content' => '',
										    'price' => $size->price,
										    //'amount' => isset($products_amount[(int)$product->product_id]) ? $products_amount[(int)$product->product_id] : 0,
										    'provider' => 2,
										    'main_product' => $color->sizes[0]->id,
										    //'attr' => json_encode($attr, JSON_UNESCAPED_UNICODE)
										);

										if(!empty($size->size)) $attr[]= $wpdb->prepare("(%d,%d,%s,%s,%d)", '', (string)(((int) $size->id) * 10), 'size_code', $size->size, 2);
 
									}else{
										
										//далее добавляем вариации
										$data = array(
										    'id' => '',
										    'provider_id' => (int) $size->id,
										    'product_group' => '0',
										    'sku' => $size->article,
										    'name' => $name,
										    'content' => '',
										    'price' => $size->price,
										    //'amount' => isset($products_amount[(int)$product->product_id]) ? $products_amount[(int)$product->product_id] : 0,
										    'provider' => 2,
										    'main_product' => $color->sizes[0]->id,
										    //'attr' => json_encode($attr, JSON_UNESCAPED_UNICODE)
										);

										if(!empty($size->size)) $attr[]= $wpdb->prepare("(%d,%d,%s,%s,%d)", '', $size->id, 'size_code', $size->size, 2);
									}

								}else{
									
									//Обычный товар
									$data = array(
									    'id' => '',
									    'provider_id' => (int) $size->id,
									    'product_group' => isset($product->main_id) ? $product->main_id : 0,
									    'sku' => $size->article,
									    'name' => $name,
									    'content' => preg_replace("#<a[^>]*?>(.*?)<\/a>#is", "$1", $product->info),
									    'price' => $size->price,
									    //'amount' => isset($products_amount[(int)$product->product_id]) ? $products_amount[(int)$product->product_id] : 0,
									    'provider' => 2,
									    'main_product' => '0',
									    //'attr' => json_encode($attr, JSON_UNESCAPED_UNICODE)
									);

									if(!empty($product->brand)) 	$attr[]	= $wpdb->prepare("(%d,%d,%s,%s,%d)", '', $size->id, 'brand', $product->brand, 2);
									if(!empty($product->material)) 	$attr[] = $wpdb->prepare("(%d,%d,%s,%s,%d)", '', $size->id, 'material', $product->material, 2);
									if(!empty($product->volume)) 	$attr[] = $wpdb->prepare("(%d,%d,%s,%s,%d)", '', $size->id, 'volume', $product->volume, 2);
									if(!empty($product->memory)) 	$attr[] = $wpdb->prepare("(%d,%d,%s,%s,%d)", '', $size->id, 'memory', $product->memory, 2);
									if(!empty($product->cover)) 	$attr[] = $wpdb->prepare("(%d,%d,%s,%s,%d)", '', $size->id, 'cover', $product->cover, 2);
									if(!empty($product->format)) 	$attr[] = $wpdb->prepare("(%d,%d,%s,%s,%d)", '', $size->id, 'format', $product->format, 2);
									if(!empty($product->weight)) 	$attr[] = $wpdb->prepare("(%d,%d,%s,%s,%d)", '', $size->id, 'weight', $product->weight, 2);
									if(!empty($product->size)) 		$attr[] = $wpdb->prepare("(%d,%d,%s,%s,%d)", '', $size->id, 'product_code', $product->size, 2);
									
									//цвет
									if(!empty($color->color->name)) $attr[]= $wpdb->prepare("(%d,%d,%s,%s,%d)", '', $size->id, 'color', $color->color->id.'|'.$color->color->name, 2);
									
									//Количество в упаковке
									if($box_count) $attr[]= $wpdb->prepare("(%d,%d,%s,%s,%d)", '', $size->id, 'pack', json_encode(array('amount' => $box_count), JSON_UNESCAPED_UNICODE), 2);

									//добавляем нанесение
									if(!empty($works)) {
										foreach($works as $work){
											$parts = explode(':', $work);
											$work = json_encode(array('name' => $parts[0], 'description' => $parts[1]), JSON_UNESCAPED_UNICODE);
											$attr[]= $wpdb->prepare("(%d,%d,%s,%s,%d)", '', $size->id, 'print', $work, 2);
										}
									}
								}

								$wpdb->insert(
						            "{$wpdb->prefix}import_provider_products",
						            $data,
						            array( 
						                '%d',
						                '%s', 
						                '%d', 
						                '%s',
						                '%s',
						                '%s',
						                '%f',
						                '%d',
						                '%s'
						            ) 
						        );

								$wpdb->show_errors();
//if((int)$size->id == 33803) print_r($wpdb->last_query);
//if((int)$size->id == 47923) print_r($product);

//if(count($size->remoteStocks) > 0) {print_r($size);print_r('<br><br><br>');}
								
								$remains = array();
								
								if(count($size->stores->remains)){

									foreach($size->stores->remains as $store){

										//заполняем склады тольько в Самаре и Новосибирске
										if( 'самара' == trim(mb_strtolower($store->store_code)) || 'новосибирск' == trim(mb_strtolower($store->store_code))){
											
											$remains[$store->store_code]['remains'] = $store->count;
											/*
											$data = array(
											    'id' => '',
											    'code' => (string) $store->store_code,
											    'product_id' => $size->id,
											    'amount' => $store->count,
											    'provider' => 2
											);
											
											$wpdb->insert(
									            "{$wpdb->prefix}import_provider_stores",
									            $data,
									            array( 
									                '%d',
									                '%s', 
									                '%d', 
									                '%f',
									                '%d'
									            ) 
									        );*/



									    }
									}
								}
//if(count($size->stores->remains) > 0) {print_r('<br>REMAINS');print_r($size);print_r('<br><br><br>');}

								if(count($size->stores->reserves)){

									foreach($size->stores->reserves as $reserve){

										//заполняем склады тольько в Самаре и Новосибирске
										if( 'самара' == trim(mb_strtolower($reserve->store_code)) || 'новосибирск' == trim(mb_strtolower($reserve->store_code))){
											
											$remains[$store->store_code]['reserve'] = $reserve->count;
											/*
											$data = array(
											    'id' => '',
											    'code' => (string) $store->store_code,
											    'product_id' => $size->id,
											    'amount' => $store->count,
											    'provider' => 2
											);
											
											$wpdb->insert(
									            "{$wpdb->prefix}import_provider_stores",
									            $data,
									            array( 
									                '%d',
									                '%s', 
									                '%d', 
									                '%f',
									                '%d'
									            ) 
									        );*/



									    }
									}
								}

								$remote = 0;

								if(count($size->remoteStocks)){

									$remains['remote']['remoteStock'] = 0;

									foreach($size->remoteStocks as $remoteStock){
											
											$remains['remote']['remoteStock'] = $remains['remote']['remoteStock'] + $remoteStock->count;

									}

									$remote = $remains['remote']['remoteStock'];
								}

								unset($remains['remote']);

								$stockQuery = array();
								$total_remains = $total_reserve = 0;

								foreach ($remains as $stock => $amount) {
									//$stockQuery[] = $wpdb->prepare("(%d,%s,%s,%d,%d,%d,%d,%d)", '', $stock, ($key ? $size->id : (string)(((int) $size->id) * 10)), $amount['remains'] + $amount['reserve'],  $amount['remains'], $remote, $remote, 2);
									$total_remains = $total_remains + $amount['remains'];
									$total_reserve = $total_reserve + $amount['reserve'];

								}

								$stockQuery[] = $wpdb->prepare("(%d,%s,%s,%d,%d,%d,%d,%d)", '', $stock, ($key ? $size->id : (string)(((int) $size->id) * 10)), $total_remains + $total_reserve,  $total_remains, $remote, $remote, 2);
								/*$data = array(
								    'id' => '',
								    'code' => (string) $store->store_code,
								    'product_id' => $size->id,
								    'amount' => $store->count,
								    'provider' => 2
								);
								
								$wpdb->insert(
						            "{$wpdb->prefix}import_provider_stores",
						            $data,
						            array( 
						                '%d',
						                '%s', 
						                '%d', 
						                '%f',
						                '%d'
						            ) 
						        );*/


								if(count($stockQuery)){
									$query = "INSERT INTO {$wpdb->prefix}import_provider_stores (id, code, product_id, amount, free, inwayamount, inwayfree, provider) VALUES ";
									$query .= implode( ",\n", $stockQuery );
//if((int)$size->id == 47923) {print_r($remains);print_r($query);exit;}
								
									$wpdb->query( $query );
								}
								//END  загрузка складов


								//Загрузка товаров в категории 
								foreach($product->categories as $category){
									
									$product_to_category_data[]= $wpdb->prepare("(%d,%d,%d,%d)", '', $size->id, $category, 2);

								}

								if((int) $size->id==41501){
									print_r( '<br>----------------------------------------------------------------------');
									print_r( $product);
									exit;
								}

							}//end sizes
						}
					}
				}

				//загрузка атрибутов
				if(count($attr)) {
					$query = "INSERT INTO {$wpdb->prefix}import_provider_product_attr (id, product_id, name, value, provider) VALUES ";
					$query .= implode( ",\n", $attr );

					$wpdb->query( $query );
				}

			}//end oceangifts products foreach

			//product to category insert data query
			/*$wpdb->insert(
	            "{$wpdb->prefix}import_provider_product_category",
	            $product_to_category_data
	        );*/

			$query = "INSERT INTO {$wpdb->prefix}import_provider_product_category (id, provider_id, category_id, provider) VALUES ";
			$query .= implode( ",\n", $product_to_category_data );

			$wpdb->query( $query );

			$output['status'] = 1;
			$output['message'] = 'Импорт oceangifts.ru завершен';
			//echo 'Загружено: ' . $category_count . ' категорий, '. $product_count . ' товаров.';
		}

		wp_send_json( $output );

		die();
	}



	function getCategoryDataOceanProvider($parent, $subcategories){
		
		global $wpdb;

		$table_name = "{$wpdb->prefix}import_provider_category";
		foreach($subcategories as $category){

			$data = array(
			    'id' => '',
			    'provider_id' => (int) $category->id,
			    'parent_id' => (int) $parent,
			    'name' => (string)$category->name,
			    'provider' => 2
			);
			
			$wpdb->insert(
	            $table_name,
	            $data,
	            array( 
	                '%d',
	                '%d', 
	                '%d', 
	                '%s',
	                '%d'
	            ) 
	        );
	        $wpdb->show_errors();

			if(isset($category->subcategories)) getCategoryDataOceanProvider($category->id, $category->subcategories);//$child = $category->page->children();

		}
	}
	
	add_action( 'wp_ajax_woodmart_get_import_provider_catalog', 'woodmart_get_import_provider_catalog' );
	add_action( 'wp_ajax_nopriv_woodmart_get_import_provider_catalog', 'woodmart_get_import_provider_catalog' );
}


// Анализ категорий на сайте 

function woodmart_manage_categories_page() {
	add_submenu_page( 
		'',
		'Обработка категорий',
		'Обработка категорий',
		'manage_options',
		'manage_categories',
		'woodmart_manage_categories'
	);
}

function woodmart_manage_categories(){
	global $wpdb;

	echo '<h1>Обработка категорий</h1>';

	$providers = $wpdb->get_results( $wpdb->prepare("SELECT * FROM `{$wpdb->prefix}import_providers`") );

	$categories = $wpdb->get_results( $wpdb->prepare("SELECT c.*, cs.site_category_id, t.name site_name, cs.clothes FROM `{$wpdb->prefix}import_provider_category` c LEFT JOIN `{$wpdb->prefix}import_provider_category_to_site` cs ON cs.provider_id = c.provider_id AND cs.provider = c.provider LEFT JOIN `{$wpdb->prefix}terms` t ON cs.site_category_id = t.term_id") );

	function get_provider_cat_tree($parent,$categories_data) {
	    $result = array();
	    foreach($categories_data as $category){
	        if ($parent == (string)$category->parent_id) {
	            $category->children = get_provider_cat_tree((string)$category->provider_id, $categories_data);
	            $result[] = $category;
	        }
	    }
	    return $result;
	}

	$categories = get_provider_cat_tree('1', $categories);

	$args = array(
	    'taxonomy' => 'product_cat',
	    'orderby' => 'name',
	    'order' => 'ASC',
	    'hierarchical'  => true,
	    'hide_empty' => false,
	);

	$the_query = new WP_Term_Query($args);
	$site_categories_data = $the_query->get_terms();    

	function get_cat_tree($parent,$site_categories_data) {
	    $result = array();
	    foreach($site_categories_data as $category){
	        if ($parent == $category->parent) {
	            $category->children = get_cat_tree($category->term_id,$site_categories_data);
	            $result[] = $category;
	        }
	    }
	    return $result;
	}
	
	$site_categories = get_cat_tree('0' ,$site_categories_data);

	function get_children_category($children, &$level){
		foreach($children as $child){
			switch($level){
				case 1:
					$level_string = '&nbsp;&nbsp;';
					break;
				case 2:
					$level_string = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
					break;
				case 3:
					$level_string = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
					break;
				case 4:
					$level_string = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
					break;
			}
			echo '<option class="level_'.$level.'" value="'.$child->term_id.'">'.$level_string.$child->name.'</option>';
			if(!empty($child->children)) {
				$level = $level + 1;
				get_children_category($child->children, $level);
			}
		}
		$level = $level - 1;
	}

	echo '
		<div id="poststuff">
			<label id="base-category-label" for="base-category">Категория сайта - 
				<select id="base-category" name="base-category">';
					$level = 1;
					echo '<option value=""></option>';
					foreach($site_categories as $sc){
						//echo '<option value="'.$type->product_id.'-'.$attr->name.'">'.$attr->value.'</option>';
						echo '<option value="'.$sc->term_id.'">'.$sc->name.'</option>';
						if(!empty($sc->children)) {
							get_children_category($sc->children, $level);
						}
					}

	echo '		</select>
				<button id="set_category_values" class="btn btn-primary">Выбрать</button>
			</label>

		</div>';


	echo '<table class="wp-list-table widefat fixed striped table-view-list posts">
	<thead>
		<tr>
			<th scope="col" class="manage-column column-title column-primary sortable desc"><span>Название</span></th>
			<th scope="col" class="manage-column column-title column-primary sortable desc"><span>Одежда</span></th>
			<th scope="col" class="manage-column column-date sortable asc"><span>Категория на сайте</span></th>
			<th scope="col" class="manage-column column-date sortable asc"><span>Поставщик</span></th>
		</tr>
	</thead>';

	function get_provider_children_category($children, &$level){
		
		foreach($children as $child){
			
			$checked = $child->clothes ? 'checked' : '';

			switch($level){
				case 1:
					$level_string = '&nbsp;&nbsp;';
					break;
				case 2:
					$level_string = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
					break;
				case 3:
					$level_string = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
					break;
				case 4:
					$level_string = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
					break;
			}
			echo '<tr>';
			echo '<td class="title column-title has-row-actions column-primary page-title level_'.$level.'"><input type="checkbox" name="category_matching[]" class="category_matching" data-id="'.$category->id.'" data-provider="'.$child->provider.'" value="'.$child->provider_id.'"/>'.$level_string.$child->name.'</td>';
			echo '<td><input type="checkbox" name="category_clothes[]" class="category_clothes" data-id="'.$category->id.'" data-provider="'.$child->provider.'" value="'.$child->provider_id.'" '.$checked.'/></td>';
			echo '<td class="title column-title has-row-actions column-primary page-title">'.$child->site_name.'</td>';
			echo '<td class="title column-title has-row-actions column-primary page-title">'.$providers[$child->provider - 1]->title.'</td>';
			echo '</tr>';
			if(!empty($child->children)) {
				$level = $level + 1;
				get_provider_children_category($child->children, $level);
			}
		}
		$level = $level - 1;
	}

	$level = 1;
	foreach($categories as $category){
		$checked = $category->clothes ? 'checked' : '';
		echo '<tr>';
		echo '<td class="title column-title has-row-actions column-primary page-title"><input type="checkbox" name="category_matching[]" class="category_matching" data-id="'.$category->id.'" data-provider="'.$category->provider.'" value="'.$category->provider_id.'"/>'.$category->name.'</td>';
		echo '<td><input type="checkbox" name="category_clothes[]" class="category_clothes" data-id="'.$category->id.'" data-provider="'.$child->provider.'" value="'.$child->provider_id.'" '.$checked.'/></td>';
		echo '<td class="title column-title has-row-actions column-primary page-title">'.$category->site_name.'</td>';
		echo '<td class="title column-title has-row-actions column-primary page-title">'.$providers[$category->provider - 1]->title.'</td>';
		echo '</tr>';

		if(!empty($category->children)) {
			$level = $level + 1;
			get_provider_children_category($category->children, $level);
		}
	}

	echo '</table>';
	?>
	<script>
		jQuery(function($){
			//Category
			$('#set_category_values').click(function(){
				let checked = [], ids = [], clothes = [];
				$('.category_matching:checked').each(function(){
					checked.push($(this).val()+ '|' + $(this).data('provider'));
				});

				$('.category_clothes:checked').each(function(){
					clothes.push($(this).val()+ '|' + $(this).data('provider'));
				});

				var requestData = {
			      action: 'woodmart_set_category_values',
			      categories: checked,
			      clothes: clothes,
			      base_category: $('#base-category').val()
			    };
			    
			    $.post('admin-ajax.php', requestData, function(response) {
			      if(response) {
			      	$('.message').html(response);
			      	window.location.reload();
			      	$('.category_matching:checked').each(function(){
			      		$(this).attr('checked', false);
			      	});
			      }
			    });
			});
		});
	</script>
<?
}
add_action('admin_menu', 'woodmart_manage_categories_page');











//add attachments to product item
function crb_insert_attachment_from_url($url, $parent_post_id = null) {

	if( !class_exists( 'WP_Http' ) )
		include_once( ABSPATH . WPINC . '/class-http.php' );

	$http = new WP_Http();
	$response = $http->request( $url );

	if( $response['response']['code'] != 200 ) {
		return false;
	}

	$upload = wp_upload_bits( basename($url), null, $response['body'] );
	if( !empty( $upload['error'] ) ) {
		return false;
	}

	$file_path = $upload['file'];
	$file_name = basename( $file_path );
	$file_type = wp_check_filetype( $file_name, null );
	$attachment_title = sanitize_file_name( pathinfo( $file_name, PATHINFO_FILENAME ) );
	$wp_upload_dir = wp_upload_dir();

	$post_info = array(
		'guid'           => $wp_upload_dir['url'] . '/' . $file_name,
		'post_mime_type' => $file_type['type'],
		'post_title'     => $attachment_title,
		'post_content'   => '',
		'post_status'    => 'inherit',
	);

	// Create the attachment
	$attach_id = wp_insert_attachment( $post_info, $file_path, $parent_post_id );

	// Include image.php
	require_once( ABSPATH . 'wp-admin/includes/image.php' );

	// Define attachment metadata
	$attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );

	// Assign metadata to attachment
	wp_update_attachment_metadata( $attach_id,  $attach_data );

	return $attach_id;

}






/////   HAPPYGIFTS


	function getCategoryDataHappyProvider($parent, $subcategories){
		
		global $wpdb;

		$table_name = "{$wpdb->prefix}import_provider_category";
		foreach($subcategories->Группа as $category){

			$data = array(
			    'id' => '',
			    'provider_id' => (string) $category->Ид,
			    'parent_id' => (string) $category->ИдРодителя,
			    'name' => (string) $category->Наименование,
			    'provider' => 4
			);
			
			$wpdb->insert(
	            $table_name,
	            $data,
	            array( 
	                '%d',
	                '%s', 
	                '%s', 
	                '%s',
	                '%d'
	            ) 
	        );
	        $wpdb->show_errors();

			if(isset($category->Группы)) getCategoryDataHappyProvider($category->id, $category->Группы);//$child = $category->page->children();

		}
	}

if ( ! function_exists( 'woodmart_get_import_provider_happygifts' ) ) {

	function woodmart_get_import_provider_happygifts() {
		global $wpdb;

		//$open = ftp_connect("ftp.ipg.su","21","100");
		//if(!ftp_login($open,"clients","cLiENts2010")) exit("Не могу соединиться");
		$content = simplexml_load_file('ftp://clients:cLiENts2010@ftp.ipg.su/clients/Nomenklatura/production.xml');
		//if(false === $contents = file_get_contents('ftp://clients:cLiENts2010@ftp.ipg.su//clients/Nomenklatura/catalogue.xml')){
		//	exit("Не могу прочитать файл");
		//}; 

		foreach($content->Группы->Группа as $group){

			$parent_id = (string) $group->ИдРодителя;
			if($parent_id  == '00000000-0000-0000-0000-000000000000') $parent_id = '1';
			
			$data = array(
			    'id' => '',
			    'provider_id' => (string) $group->Ид,
			    'parent_id' => $parent_id,
			    'name' => (string) $group->Наименование,
			    'provider' => 4
			);
			
			$wpdb->insert(
	            "{$wpdb->prefix}import_provider_category",
	            $data,
	            array( 
	                '%d',
	                '%s', 
	                '%s', 
	                '%s',
	                '%d'
	            ) 
	        );

	        if(isset($group->Группы)) getCategoryDataHappyProvider($group->Ид, $group->Группы);
		}

		//Загрузка товаров
		//$main_products - массив для определения главного товара и вариаций
		$main_products = array();
		$product_to_category_data = array();
$i=0;
		foreach($content->Номенклатура->Элемент as $product){

			$attr = array();
			$is_variation = false;
			$sku = $product->Артикул;
			$name = (string) $product->НаименованиеПолное;
			$main_product = 0;

			$product_group = (int)$product->ОбщийАртикулГруппы;

			if(!empty($product_group)){
				$is_variation = true;
			}

			if(!(strpos($product->Артикул, '/') === false)){

				$article = explode('/', $product->Артикул);
				if(isset($article[1]) && !empty($article[1])) $name = str_replace('_'.$article[1], '', $name);

				$sku = $article[0];
			}

			if($is_variation){
//if((string)$product->ИД == '66c10e8a-305e-11e1-bebd-001871eb2973') print_r('77');
				if(isset($main_products[$product_group][$sku][0])){
					$main_product = $main_products[$product_group][$sku][0];
				}

			}

			$data = array(
			    'id' => '',
			    'provider_id' => (string) $product->ИД,
			    'product_group' => !empty($product_group) ? $product_group : 0,
			    'sku' => $sku,
			    'name' => wp_slash($product->Описание),
			    'content' =>preg_replace("#<a[^>]*?>(.*?)<\/a>#is", "$1", $product->КомментарийНаСайт),
			    'price' => $product->РозничнаяЦена,
			    //'amount' => isset($products_amount[(int)$product->product_id]) ? $products_amount[(int)$product->product_id] : 0,
			    'provider' => 4,
			    'main_product' => $main_product,
			    //'attr' => json_encode($attr, JSON_UNESCAPED_UNICODE)
			);

			//if(trim($name)=="Кружка стеклянная"){print_r( $product);}
			
			$wpdb->insert(
	            "{$wpdb->prefix}import_provider_products",
	            $data,
	            array( 
	                '%d',
	                '%s', 
	                '%d', 
	                '%s',
	                '%s',
	                '%s',
	                '%f',
	                '%d',
	                '%s'
	            ) 
	        );

			//Атрибуты
			if(!empty($product->БрендОсн)) $attr[]= $wpdb->prepare("(%d,%s,%s,%s,%d)", '', $product->ИД, 'brand', $product->БрендОсн, 4);

			if(isset($product->РазмерОдежды) && !is_object($product->РазмерОдежды)) $attr[]= $wpdb->prepare("(%d,%s,%s,%s,%d)", '', $product->ИД, 'size', $product->РазмерОдежды, 4);
			else $attr[]= $wpdb->prepare("(%d,%s,%s,%s,%d)", '', $product->ИД, 'size', $product->Размер, 4);

			if(!empty($product->Материал)) $attr[]= $wpdb->prepare("(%d,%s,%s,%s,%d)", '', $product->ИД, 'material', $product->Материал, 4);

			$provider_4_color = '';
			if(!empty($product->Цвет)) {
				$provider_4_color = base64_encode(mb_strtolower($product->Цвет)).'|'.$product->Цвет;
				$attr[]= $wpdb->prepare("(%d,%s,%s,%s,%d)", '', $product->ИД, 'color', $provider_4_color, 4);
			}

			if(isset($product->ТипыНанесения) && !empty($product->ТипыНанесения)) {
				foreach($product->ТипыНанесения->ТипНанесения as $print)
					$attr[]= $wpdb->prepare("(%d,%s,%s,%s,%d)", '', $product->ИД, 'print', (string)$print, 4);
			}
			
			$query = "INSERT INTO {$wpdb->prefix}import_provider_product_attr (id, product_id, name, value, provider) VALUES ";
			
			//ищем основной товар или нет, т.к. у вариантов основного товара овторяются те же атрибуты кроме размера
			if(!isset($main_products[$product_group][$sku][0])){
			
				$query .= implode( ",\n", $attr );
				
			}else{
				$attr_size = array();
				if(isset($product->РазмерОдежды) && !is_object($product->РазмерОдежды)) $attr_size[]= $wpdb->prepare("(%d,%s,%s,%s,%d)", '', $product->ИД, 'size', $product->РазмерОдежды, 4);
				else $attr_size[]= $wpdb->prepare("(%d,%s,%s,%s,%d)", '', $product->ИД, 'size', $product->Размер, 4);

				if(!empty($product->Цвет)) $attr_size[]= $wpdb->prepare("(%d,%s,%s,%s,%d)", '', $product->ИД, 'color',  base64_encode(mb_strtolower($product->Цвет)).'|'.$product->Цвет, 4);

				$query .= implode( ",\n", $attr_size );
			}

			$wpdb->query( $query );

			//Загрузка товаров в категорию
			$product_to_category_data[]= $wpdb->prepare("(%d,%s,%s,%d)", '', $product->ИД, $product->ИДРодителя, 4);

	        if(!empty($product_group) && !isset($main_products[$product_group][$sku][0])) $main_products[$product_group][$sku][] = (string) $product->ИД;
/*if($product_group == 711418) {
	print_r($product);
print_r('<br><br>');
}*/

		}

		$query = "INSERT INTO {$wpdb->prefix}import_provider_product_category (id, provider_id, category_id, provider) VALUES ";
		$query .= implode( ",\n", $product_to_category_data );
		$wpdb->query( $query );

		for($s=0; $s<=8; $s++){
			$stores = simplexml_load_file("ftp://clients:cLiENts2010@ftp.ipg.su/clients/Ostatki/store$s.xml");
			//print_r("ftp://clients:cLiENts2010@ftp.ipg.su/clients/Ostatki/store$s.xml".'<br>');

			foreach($stores->Остатки->Остаток as $store){
				$product_id = $store->ИД;
				$amount = $store->Свободный;
				$keep = $store->Занятый;
				$wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}import_provider_products SET amount = '%f', free = '%s' WHERE provider_id = '%s' and provider = 4", $amount, $amount - $keep, $product_id));
			}
		}


// $i++;break;
//print_r($main_products);
		echo 'Загружены товары HAPPYGIFTS';
		die();
	}

	add_action( 'wp_ajax_woodmart_get_import_provider_happygifts', 'woodmart_get_import_provider_happygifts' );
	add_action( 'wp_ajax_nopriv_woodmart_get_import_provider_happygifts', 'woodmart_get_import_provider_happygifts' );
}






//Загрузка отсатков
function woodmart_import_stores_amount() {
	global $wpdb;

	//$post_ids = $wpdb->get_results( $wpdb->prepare("SELECT DISTINCT pi.product_id, pi.post_id, s.amount, s.free  FROM `{$wpdb->prefix}import_provider_product_images` pi LEFT JOIN {$wpdb->prefix}import_provider_stores s ON pi.product_id = s.product_id  AND pi.provider = s.provider WHERE pi.post_id > 0 AND pi.processed = 1 ") );




	$url = "http://17455_xmlexport:Q5ytqJkw@api2.gifts.ru/export/v2/catalogue/stock.xml";

	if(($xml = simplexml_load_file($url)) !== FALSE){

		if(count($xml)){

			//товары магазина
			$products = array();

			$results = $wpdb->get_results( $wpdb->prepare( "SELECT p.ID, m.meta_value as sku, p.post_type FROM $wpdb->posts p INNER JOIN $wpdb->postmeta m ON p.ID = m.post_id WHERE (p.post_type = 'product' OR p.post_type = 'product_variation') AND m.meta_key='_sku'") );

			if (count($results)) {
			    foreach ($results as $product) {
			        $products[(string)$product->sku]['ID'] = $product->ID;
			        $products[(string)$product->sku]['post_type'] = $product->post_type;
			    }
			}


			//товары поcтавщика
			$provider_products = array();
			foreach($xml->stock as $stock){
				//if((int)$stock->code == 2396) {print_r('<br><br>Количество  ');print_r($stock);	}
				//if(strpos($stock->code !== false) {print_r('<br><br>Количество  ');print_r($stock);	}
 				$provider_products[(string)$stock->product_id]['amount'] = (float)$stock->amount;
				$provider_products[(string)$stock->product_id]['free'] = (float)$stock->free;
				$provider_products[(string)$stock->product_id]['inwayamount'] = (float)$stock->inwayamount;
				$provider_products[(string)$stock->product_id]['inwayfree'] = (float)$stock->inwayfree;
			}
			
			

			$results = $wpdb->get_results( $wpdb->prepare( "SELECT p.provider_id, p.sku FROM {$wpdb->prefix}import_provider_products p") );

			if(count($results)){
				foreach($results as $provider_product){
					//$provider_products[$provider_product->provider_id]['sku'] = $provider_product->sku;
					$key = (string)$provider_product->sku;

					if(isset( $products[ $key ] )){
						if($products[$key]['post_type'] == 'product'){
							$WC_product = new WC_Product( $products[$key]['ID'] );
							
							update_post_meta( $products[$key]['ID'], '_stock_qty',  $provider_products[$products[$key]['ID']]['free']);

							$amount = array();

							$amount[0]['provider'] = 1; // шаблон store + поставщик + номер склада
							$amount[0]['amount'] = $provider_products[$provider_product->provider_id]['amount'];
							$amount[0]['free'] = $provider_products[$provider_product->provider_id]['free'];
							$amount[0]['inwayamount'] = $provider_products[$provider_product->provider_id]['inwayamount'];
							$amount[0]['inwayfree'] = $provider_products[$provider_product->provider_id]['inwayfree'];
							$amount[0]['store_title'] = '';

							update_post_meta( $products[$key]['ID'], '_stock_store_qty',  $amount );

						}
						else{
							$WC_product = new WC_Product_Variation( $products[$key]['ID'] );

							//$WC_product->set_stock_quantity( $provider_products[$products[$key]['ID']]['free']  );

							$amount = array();

							$amount[0]['provider'] = 1; // шаблон store + поставщик + номер склада
							$amount[0]['amount'] = $provider_products[$provider_product->provider_id]['amount'];
							$amount[0]['free'] = $provider_products[$provider_product->provider_id]['free'];
							$amount[0]['inwayamount'] = $provider_products[$provider_product->provider_id]['inwayamount'];
							$amount[0]['inwayfree'] = $provider_products[$provider_product->provider_id]['inwayfree'];
							$amount[0]['store_title'] = '';

							update_post_meta( $products[$key]['ID'], '_stock_store_qty',  $amount );
						}

						$WC_product->save();

					}

				}
			}





		}

	}

}



// Загрузка фильтров 
function woodmart_import_filters() {
	global $wpdb;

	$url = "http://17455_xmlexport:Q5ytqJkw@api2.gifts.ru/export/v2/catalogue/filters.xml";

	if(($xml = simplexml_load_file($url)) !== FALSE){

		if(count($xml)){

			$wpdb->query("TRUNCATE {$wpdb->prefix}import_provider_filter_types");
			$wpdb->query("TRUNCATE {$wpdb->prefix}import_provider_filters");

			$filter_types = array();

			foreach($xml->filtertypes->filtertype as $filtertype){
				//if((int)$product->product_id == 1369) print_r($product);

				$filter_types[] = $wpdb->prepare("('', %d, %s, 1)", $filtertype->filtertypeid, $filtertype->filtertypename);

				$filters = array();

				foreach($filtertype->filters->filter as $filter){
					$filters[] = $wpdb->prepare("('', %d, %d, %s)", $filtertype->filtertypeid, $filter->filterid, $filter->filtername->__tostring());
				}

				$filter_query = "INSERT INTO {$wpdb->prefix}import_provider_filters (id, filtertypeid, filterid, filtername) VALUES ";
				$filter_query .= implode( ",\n", $filters );
				$wpdb->query( $filter_query );
			}
			

			$query = "INSERT INTO {$wpdb->prefix}import_provider_filter_types (id, filtertypeid, filtertypename, provider) VALUES ";
			$query .= implode( ",\n", $filter_types );
			$wpdb->query( $query );

			echo 'Фильтры загружены';
		}
	}
}















//Дополнительные хуки
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );
add_action( 'woocommerce_single_product_summary', 'mod_woodmart_after_add_to_compare_single_btn', 34 );

if ( ! function_exists( 'mod_woodmart_after_add_to_compare_single_btn' ) ) {
	function mod_woodmart_after_add_to_compare_single_btn() {
		global $product;

		$clothes = get_post_meta( $product->id, '_clothes', true );
?>
<div class="single-variation-product-attr">
	<hr>
	<div class="row no-gutters">
		<div class="col-lg-5 col-md-12">

			<? if( $pack_amount = $product->get_attribute('pa_pack_amount') ){

				$label_name = wc_attribute_label('pa_pack_amount');
			?>
				<div class="product_meta"> 
					<span class="pack_wrapper">
						<span class="label"><?=$label_name?>:</span>
						<span class="pack"><?=$pack_amount?></span>
					</span>
				</div>
			<? } ?>

			<? if( $pack_amount = $product->get_attribute('pa_pack_volume') ){

				$label_name = wc_attribute_label('pa_pack_volume');
			?>
				<div class="product_meta"> 
					<span class="pack_wrapper">
						<span class="label"><?=$label_name?>:</span>
						<span class="pack"><?=$pack_amount?></span>
					</span>
				</div>
			<? } ?>

			<? woocommerce_template_single_meta(); ?>
		</div>
		<div class="col-lg-7 col-md-12">
			<div class="row">
				<div class="col-lg-6 col-md-12">
					<table class="woocommerce-product-attributes shop_attributes"><tbody>
					<? if( $weight_value = $product->get_attribute('pa_weight') ){ 
						$label_name = wc_attribute_label('pa_weight');  ?>
						<tr class="woocommerce-product-attributes-item woocommerce-product-attributes-item--attribute_pa_weight">
							<th class="woocommerce-product-attributes-item__label"><?=$label_name?>:</th>
							<td class="woocommerce-product-attributes-item__value"><?=$weight_value?></p></td>
						</tr>
					<? } ?>
					</tbody></table>
				</div>
				<div class="col-lg-6 col-md-12">
					<table class="woocommerce-product-attributes shop_attributes"><tbody>
					<? if( $brand_value = $product->get_attribute('pa_brend') ){ 
						$label_name = wc_attribute_label('pa_brend');  ?>
						<tr class="woocommerce-product-attributes-item woocommerce-product-attributes-item--attribute_pa_material">
							<th class="woocommerce-product-attributes-item__label"><?=$label_name?>:</th>
							<td class="woocommerce-product-attributes-item__value"><p><?=$brand_value?></p></td>
						</tr>
					<? } ?>
					</tbody></table>
				</div>
			</div>

			<div class="woodmart-mod-store-amount">
			<?

			$stores = get_post_meta($product->id, '_stock_store_qty', true);

			//вывод атрибутов
			if(!empty($stores) && count($stores)) {
				echo '<table class="table stores-stock stock in-stock woocommerce-product-stock"><tbody>
						<tr><th>Склад</th><th>Всего</th><th>Свободно</th><th>Резерв</th></tr>';
				foreach($stores as $store_data){
					if(empty($store_data['store_title'])) $store_data['store_title'] = "Москва";

					echo '<tr><td>'.$store_data['store_title'].'</td><td>'.$store_data['amount'].'</td><td>'.$store_data['free'].'</td><td>'.($store_data['amount'] - $store_data['free']).'</td></tr>';
					if( $store_data['inwayamount'] > 0) {
						echo '<tr><td>Удал. склад</td><td>'.$store_data['inwayamount'].'</td><td>'.$store_data['inwayfree'].'</td><td>'.($store_data['inwayamount'] - $store_data['inwayfree']).'</td></tr>';
					}
				}
				echo '</table>';
			}
			?>
			</div>
		</div>
	</div>
</div>
<?	}
}


add_action( 'mod_woocommerce_variable_product_store_amount', 'get_variable_product_store_amount', 10 ,  1);

if ( ! function_exists( 'get_variable_product_store_amount' ) ) {
	function get_variable_product_store_amount($available_variations) {
		global $product;

		if(!empty($available_variations)): 
			$stores_amount = $stores_title = array();

			$inwayamount = 0;// признак удаленного склада

			foreach($available_variations as $key => $available_variation){
				$stores = get_post_meta($available_variation['variation_id'], '_stock_store_qty', true);

				if(!empty($stores))
					foreach($stores as $store_data){
						if(empty($store_data['store_title'])) $store_data['store_title'] = "Москва";

						if(!in_array($store_data['store_title'], $stores_title )) $stores_title[] = $store_data['store_title'];

						$stores_amount[$available_variation['attributes']['attribute_pa_size']]['variation_id'] = $available_variation['variation_id'];
						$stores_amount[$available_variation['attributes']['attribute_pa_size']][$store_data['store_title']]['amount'] = $store_data['amount'];
						$stores_amount[$available_variation['attributes']['attribute_pa_size']][$store_data['store_title']]['free'] = $store_data['free'];
						$stores_amount[$available_variation['attributes']['attribute_pa_size']][$store_data['store_title']]['inwayamount'] = $store_data['inwayamount'];
						$stores_amount[$available_variation['attributes']['attribute_pa_size']][$store_data['store_title']]['inwayfree'] = $store_data['inwayfree'];

						if($store_data['inwayamount'] > 0) $inwayamount = 1;

						/*'<tr><td>'.$store_data['store_title'].'</td><td>'..'</td><td>'.$store_data['free'].'</td><td>'.($store_data['amount'] - $store_data['free']).'</td></tr>';
						if( $store_data['inwayamount'] > 0) echo '<tr><td>Удаленный склад</td><td>'.$store_data['inwayamount'].'</td><td>'.$store_data['inwayfree'].'</td><td>'.$store_data['inwayamount'] - $store_data['inwayfree'].'</td></tr>';*/
					}

				

				//if(empty())
			} ?>

		<table class="table stock stores-stock in-stock woocommerce-product-stock"><tbody>
			<tr><th>Размер</th><th>Склад</th><?= $inwayamount ? '<th>Удаленный склад</th>' : '' ?><th>Тираж</th></tr>

			<?
			$store_template = '<tr><td>_SIZE_</td><td>_STOREAMOUNT_</td><td>_INWAY_</td><td>_EDITION_</td></tr>';
			foreach($stores_amount as $variant_size => $variant_data){

				$td = '<td>'.strtoupper($variant_size).'</td>';
				
				foreach($stores_title as $s_title){
					if(isset($stores_amount[$variant_size][$s_title])) {
						$td .= '<td>'.$stores_amount[$variant_size][$s_title]['amount'].'</td>';
						if($stores_amount[$variant_size][$s_title]['inwayamount'] > 0) $td .= '<td>'.$stores_amount[$variant_size][$s_title]['inwayamount'].'</td>';
						$td .= '<td><span class="minus">-</span>&nbsp;<input type="number" data-variation_slug="'.$variant_size.'" data-variation="'.$variant_data['variation_id'].'" class="edition" id="edition_amount" placeholder="'.$stores_amount[$variant_size][$s_title]['free'].'" value="">&nbsp;<span class="plus">+</span></td>';
					}
				}

				//echo '<td>'..'</td><td>_INWAY_</td><td>_EDITION_</td></tr>';
				echo '<tr>'.$td.'</tr>';
			}



			?>
		</tbody></table>
		
		<?endif;
	}
}




//изменение двойной цены
/*add_filter( 'woocommerce_get_price_html', 'woodmart_mod_woocommerce_price_html', 100, 2 );
function woodmart_mod_woocommerce_price_html( $price, $product ){
    
	print_r($price.'1');
    return 'Was:' . str_replace( '<ins>', ' Now:<ins>', $price );
}*/

add_filter( 'woocommerce_variable_price_html', 'woodmart_mod_woocommerce_variable_price_html', 10, 2 );
function woodmart_mod_woocommerce_variable_price_html( $price, $product ){
    
	$prices = array( $product->get_variation_price( 'min', true ), $product->get_variation_price( 'max', true ) );
    $price = $prices[0] !== $prices[1] ? sprintf( __( '%1$s', 'woocommerce' ), wc_price( $prices[0] ) ) : wc_price( $prices[0] );

    // Sale Price
    $prices = array( $product->get_variation_regular_price( 'min', true ), $product->get_variation_regular_price( 'max', true ) );
    sort( $prices );
    $saleprice = $prices[0] !== $prices[1] ? sprintf( __( '%1$s', 'woocommerce' ), wc_price( $prices[0] ) ) : wc_price( $prices[0] );

    if ( $price !== $saleprice ) {
        $price = '<del>' . $saleprice . '</del> <ins>' . $price . '</ins>';
    }

    return $price;
    
}


add_action('woocommerce_add_to_cart', 'custome_add_to_cart', 10, 6);

function custome_add_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
    global $woocommerce;

    $variations = json_decode(stripslashes($_POST['order_store_variations']), true);

    unset($variations[0]);

    foreach($variations as $variation_data){

    	remove_action('woocommerce_add_to_cart', __FUNCTION__);

    	$added = WC()->cart->add_to_cart( $product_id, $variation_data['variation_count'], $variation_data['variation_id'], wc_get_product_variation_attributes( $variation_data['variation_id'] ) );
    }

}


add_action('woocommerce_before_variations_form', 'custom_mod_woocommerce_before_variations_form', 10, 6);

function custom_mod_woocommerce_before_variations_form() {
    global $woocommerce, $product;

	$clothes = get_post_meta( $product->id, '_clothes', true );

	if($clothes){
	?>
	<div class="single-variation-product-attr">
		<div class="row no-gutters">
			<div class="col-lg-8 col-md-12">

				<? if( $clothes && $material_value = $product->get_attribute('pa_material') ){ 
					$label_name = wc_attribute_label('pa_material');  ?>
					<div class="product_meta">
						<div class="pack_wrapper">
							<span class="label"><?=$label_name?> :</span>
							<span class="attribute-pa_material"><?php echo wp_kses_post( $material_value );?></span>
						</div>
					</div>
				<? } ?>

				<? if( $clothes && $size_value = $product->get_attribute('pa_pazmery') ){ 
					$label_name = wc_attribute_label('pa_pazmery');  ?>
					<div class="product_meta">
						<span class="pack_wrapper">
							<span class="label"><?=$label_name?> :</span>
							<span class="attribute-pa_material"><?php echo wp_kses_post( $size_value );?></span>
						</span>
					</div>
				<? } ?>

				<?php
				if( $clothes ):
					$crosssell_ids = get_post_meta( get_the_ID(), '_crosssell_ids' );
					$crosssell_ids=$crosssell_ids[0];

					if(!empty($crosssell_ids) && count($crosssell_ids) > 0){

						$args = array(
						'post_type' => 'product',
						'ignore_sticky_posts' => 1,
						'no_found_rows' => 1,
						'posts_per_page' => -1,
						'orderby' => $orderby,
						'post__in' => $crosssell_ids
						);

						$products = new WP_Query( $args );

						$woocommerce_loop['columns'] = apply_filters( 'woocommerce_cross_sells_columns', $columns );

						if ( $products->have_posts() ) : ?>

							
							<div class="row no-gutters cross-sells-group">
								<h5 class="col-md-2">Цвет :</h5>

							<?php while ( $products->have_posts() ) : $products->the_post(); ?>

							<?php //wc_get_template( 'single-product/add-to-cart/grouped.php' ); //
							wc_get_template_part( 'content', 'product-group' ); ?>

							<?php endwhile; // end of the loop. ?>


							</div>

						<?php endif;

					}

					wp_reset_query(); 
				endif;?>
			</div>
		</div>
	</div>
	<?
	}

}