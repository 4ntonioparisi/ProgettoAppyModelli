<?php 

require_once "Secure_Controller.php";

class Items extends Secure_Controller
{
	public function __construct()
	{
		parent::__construct('items');

		$this->load->library('item_lib');
	}
	
	public function index()
	{
		$data['table_headers'] = $this->xss_clean(get_items_manage_table_headers());
		
		$data['stock_location'] = $this->xss_clean($this->item_lib->get_item_location());
		$data['stock_locations'] = $this->xss_clean($this->Stock_location->get_allowed_locations());

		// filters that will be loaded in the multiselect dropdown
		$data['filters'] = array('empty_upc' => $this->lang->line('items_empty_upc_items'),
			'low_inventory' => $this->lang->line('items_low_inventory_items'),
			'is_serialized' => $this->lang->line('items_serialized_items'),
			'no_description' => $this->lang->line('items_no_description_items'),
			'search_custom' => $this->lang->line('items_search_custom_items'),
			'is_deleted' => $this->lang->line('items_is_deleted'));

		$this->load->view('items/manage');
	}

	/*
	Returns Items table data rows. This will be called with AJAX.
	*/
	public function search()
	{
		$search = $this->input->get('search');

		$this->item_lib->set_item_location($this->input->get('stock_location'));

		$filters = array('start_date' => $this->input->get('start_date'),
						'end_date' => $this->input->get('end_date'),
						'stock_location_id' => $this->item_lib->get_item_location(),
						'empty_upc' => false,
						'low_inventory' => false, 
						'is_serialized' => false,
						'no_description' => false,
						'search_custom' => false,
						'is_deleted' => false);
		
		// check if any filter is set in the multiselect dropdown
		$filledup = array_fill_keys($this->input->get('filters'), true);
		$filters = array_merge($filters, $filledup);

		$items = $this->Item->search();

		$total_rows = $this->Item->get_found_rows($search, $filters);

		$data_rows = array();
		foreach($items->result() as $item)
		{
			$data_rows[] = $this->xss_clean(get_item_data_row($item, $this));
			if($item->pic_filename!='')
			{
				$this->_update_pic_filename($item);
			}
		}

		echo json_encode(array('total' => $total_rows, 'rows' => $data_rows));
	}
	
	public function pic_thumb($pic_filename)
	{
		$this->load->helper('file');
		$this->load->library('image_lib');

		// in this context, $pic_filename always has .ext
		$ext = pathinfo($pic_filename, PATHINFO_EXTENSION);
		$images = glob('./uploads/item_pics/' . $pic_filename);

		// make sure we pick only the file name, without extension
		$base_path = './uploads/item_pics/' . pathinfo($pic_filename, PATHINFO_FILENAME);
		if(sizeof($images) > 0)
		{
			$image_path = $images[0];
			$thumb_path = $base_path . $this->image_lib->thumb_marker . '.' . $ext;
			if(sizeof($images) < 2)
			{
				$config['image_library'] = 'gd2';
				$config['source_image']  = $image_path;
				$config['maintain_ratio'] = true;
				$config['create_thumb'] = true;
				$config['width'] = 52;
				$config['height'] = 32;
				$this->image_lib->initialize($config);
				$thumb_path = $this->image_lib->full_dst_path;
			}
			$this->output->set_content_type(get_mime_by_extension($thumb_path));
			$this->output->set_output(file_get_contents($thumb_path));
		}
	}

	/*
	Gives search suggestions based on what is being searched for
	*/
	public function suggest_search()
	{
		$suggestions = $this->xss_clean($this->Item->get_search_suggestions($this->input->post_get('term'),
			array('search_custom' => $this->input->post('search_custom'), 'is_deleted' => $this->input->post('is_deleted') != null), false));

		echo json_encode($suggestions);
	}

	public function check_kit_exists()
	{
		if ($this->input->post('item_number') === -1)
		{
			$exists = $this->Item_kit->item_kit_exists_for_name($this->input->post('name'));
		}
		else
		{
			$exists = false;
		}
		echo !$exists ? 'true' : 'false';
	}

	private function _handle_image_upload()
	{
		/* Let files be uploaded with their original name */
		
		// load upload library
		$config = array('upload_path' => './uploads/item_pics/',
			'allowed_types' => 'gif|jpg|png',
			'max_size' => '100',
			'max_width' => '640',
			'max_height' => '480'
		);
		$this->load->library('upload', $config);
		$this->upload->do_upload('item_image');
		
		return strlen($this->upload->display_errors()) == 0 || !strcmp($this->upload->display_errors(), '<p>'.$this->lang->line('upload_no_file_selected').'</p>');
	}

	public function remove_logo()
	{
		$result = $this->Item->save();

		echo json_encode(array('success' => $result));
	}

	public function save_inventory($item_id = -1)
	{	
		$employee_id = $this->Employee->get_logged_in_employee_info()->person_id;
		
		$location_id = $this->input->post('stock_location');
		$inv_data = array(
			'trans_date' => date('Y-m-d H:i:s'),
			'trans_items' => $item_id,
			'trans_user' => $employee_id,
			'trans_location' => $location_id,
			'trans_comment' => $this->input->post('trans_comment'),
			'trans_inventory' => parse_decimals($this->input->post('newquantity'))
		);
		
		$this->Inventory->insert($inv_data);
		
		//Update stock quantity

		if($this->Item_quantity->save())
		{
			$message = $this->xss_clean($this->lang->line('items_successful_updating') . ' ' . $cur_item_info->name);
			
			echo json_encode(array('success' => true, 'message' => $message, 'id' => $item_id));
		}
		else//failure
		{
			$message = $this->xss_clean($this->lang->line('items_error_adding_updating') . ' ' . $cur_item_info->name);
			
			echo json_encode(array('success' => false, 'message' => $message, 'id' => -1));
		}
	}

	public function bulk_update()
	{
		$items_to_update = $this->input->post('item_ids');
		$item_data = array();

		foreach($_POST as $key => $value)
		{		
			//This field is nullable, so treat it differently
			if($key == 'supplier_id' && $value != '')
			{	
				$item_data["$key"] = $value;
			}
			elseif($value != '' && !(in_array($key, array('item_ids', 'tax_names', 'tax_percents'))))
			{
				$item_data["$key"] = $value;
			}
		}

		//Item data could be empty if tax information is being updated
		if(empty($item_data) || $this->Item->update_multiple($item_data, $items_to_update))
		{
			$items_taxes_data = array();
			$tax_names = $this->input->post('tax_names');
			$tax_percents = $this->input->post('tax_percents');
			$tax_updated = false;
			$count = count($tax_percents);
			for ($k = 0; $k < $count; ++$k)
			{		
				if(!empty($tax_names[$k]) && is_numeric($tax_percents[$k]))
				{
					$tax_updated = true;
					
					$items_taxes_data[] = array('name' => $tax_names[$k], 'percent' => $tax_percents[$k]);
				}
			}
			
			if($tax_updated)
			{
				$this->Item_taxes->save_multiple($items_taxes_data, $items_to_update);
			}

			echo json_encode(array('success' => true, 'message' => $this->lang->line('items_successful_bulk_edit'), 'id' => $this->xss_clean($items_to_update)));
		}
		else
		{
			echo json_encode(array('success' => false, 'message' => $this->lang->line('items_error_updating_multiple')));
		}
	}

	public function delete()
	{
		$items_to_delete = $this->input->post('ids');

		if($this->Item->delete_list($items_to_delete))
		{
			$message = $this->lang->line('items_successful_deleted') . ' ' . count($items_to_delete) . ' ' . $this->lang->line('items_one_or_multiple');
			echo json_encode(array('success' => true, 'message' => $message));
		}
		else
		{
			echo json_encode(array('success' => false, 'message' => $this->lang->line('items_cannot_be_deleted')));
		}
	}

	/*
	Items import from excel spreadsheet
	*/
	public function excel()
	{
		$name = 'import_items.csv';
		$data = file_get_contents('../' . $name);
		force_download($name, $data);
	}
	
	public function excel_import()
	{
		$this->load->view('items/form_excel_import');
	}

	public function do_excel_import()
	{
		if($_FILES['file_path']['error'] != UPLOAD_ERR_OK)
		{
			echo json_encode(array('success' => false, 'message' => $this->lang->line('items_excel_import_failed')));
		}
		else
		{
			if(($handle = fopen($_FILES['file_path']['tmp_name'], 'r')) !== false)
			{
				// Skip the first row as it's the table description
				fgetcsv($handle);
				$i = 1;
				
				$failCodes = array();
		
				while(($data = fgetcsv($handle)) !== false)
				{
					// XSS file data sanity check
					$data = $this->xss_clean($data);
					
					/* haven't touched this so old templates will work, or so I guess... */
					if(sizeof($data) >= 23)
					{
						$item_data = array(
							'name'					=> $data[1],
							'description'			=> $data[11],
							'category'				=> $data[2],
							'cost_price'			=> $data[4],
							'unit_price'			=> $data[5],
							'reorder_level'			=> $data[10],
							'supplier_id'			=> $this->Supplier->exists($data[3]) ? $data[3] : null,
							'allow_alt_description'	=> $data[12] != '' ? '1' : '0',
							'is_serialized'			=> $data[13] != '' ? '1' : '0',
							'custom1'				=> $data[14],
							'custom2'				=> $data[15],
							'custom3'				=> $data[16],
							'custom4'				=> $data[17],
							'custom5'				=> $data[18],
							'custom6'				=> $data[19],
							'custom7'				=> $data[20],
							'custom8'				=> $data[21],
							'custom9'				=> $data[22],
							'custom10'				=> $data[23]
						);

						/* we could do something like this, however, the effectiveness of
						  this is rather limited, since for now, you have to upload files manually
						  into that directory, so you really can do whatever you want, this probably
						  needs further discussion  */

						$pic_file = $data[24];
						/*if(strcmp('.htaccess', $pic_file)==0) {
							$pic_file='';
						}*/
						$item_data['pic_filename'] = $pic_file;

						$item_number = $data[0];
						$invalidated = false;
						if($item_number != '')
						{
							$item_data['item_number'] = $item_number;
							$invalidated = $this->Item->item_number_exists($item_number);
						}
					}
					else 
					{
						$invalidated = true;
					}

					if(!$invalidated && $this->Item->save($item_data))
					{
						$items_taxes_data = null;
						//tax 1
						if(is_numeric($data[7]) && $data[6] != '')
						{
							$items_taxes_data[] = array('name' => $data[6], 'percent' => $data[7] );
						}

						//tax 2
						if(is_numeric($data[9]) && $data[8] != '')
						{
							$items_taxes_data[] = array('name' => $data[8], 'percent' => $data[9] );
						}

						// save tax values
						if(count($items_taxes_data) > 0)
						{
							$this->Item_taxes->save();
						}

						// quantities & inventory Info
						$employee_id = $this->Employee->get_logged_in_employee_info()->person_id;
						$comment ='Qty CSV Imported';

						$cols = count($data);

						// array to store information if location got a quantity
						$allowed_locations = $this->Stock_location->get_allowed_locations();
						for ($col = 25; $col < $cols; $col = $col + 2)
						{
							$location_id = $data[$col];
							if(array_key_exists($location_id, $allowed_locations))
							{
								$this->Item_quantity->save();

								$excel_data = array(
									'trans_items' => $item_data['item_id'],
									'trans_user' => $employee_id,
									'trans_comment' => $comment,
									'trans_location' => $data[$col],
									'trans_inventory' => $data[$col + 1]
								);
								
								$this->Inventory->insert($excel_data);
								unset($allowed_locations[$location_id]);
							}
						}

						/*
						 * now iterate through the array and check for which location_id no entry into item_quantities was made yet
						 * those get an entry with quantity as 0.
						 * unfortunately a bit duplicate code from above...
						 */
						foreach($allowed_locations as $location_id => $location_name)
						{
							$this->Item_quantity->save();

							$excel_data = array(
								'trans_items' => $item_data['item_id'],
								'trans_user' => $employee_id,
								'trans_comment' => $comment,
								'trans_location' => $location_id,
								'trans_inventory' => 0
							);

							$this->Inventory->insert($excel_data);
						}
					}
					else //insert or update item failure
					{
						$failCodes[] = $i;
					}

					++$i;
				}

				if(count($failCodes) > 0)
				{
					$message = $this->lang->line('items_excel_import_partially_failed') . ' (' . count($failCodes) . '): ' . implode(', ', $failCodes);
					
					echo json_encode(array('success' => false, 'message' => $message));
				}
				else
				{
					echo json_encode(array('success' => true, 'message' => $this->lang->line('items_excel_import_success')));
				}
			}
			else 
			{
				echo json_encode(array('success' => false, 'message' => $this->lang->line('items_excel_import_nodata_wrongformat')));
			}
		}
	}

	/**
	 * Guess whether file extension is not in the table field,
	 * if it isn't, then it's an old-format (formerly pic_id) field,
	 * so we guess the right filename and update the table
	 * @param $item the item to update
	 */
	private function _update_pic_filename($item)
	{
		$filename = pathinfo($item->pic_filename, PATHINFO_FILENAME);
		if($filename=='')
		{
			// if the field is empty there's nothing to check
			return;
		}
		
		$ext = pathinfo($item->pic_filename, PATHINFO_EXTENSION);
		if ($ext == '') {
			$images = glob('./uploads/item_pics/' . $item->pic_filename . '.*');
			if (sizeof($images) > 0) {
				$this->Item->save();
			}
		}
	}
}
?>
