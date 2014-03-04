<?php
	require('lib/SimpleSite/db.class.php.php');

	// Change this to suit your PrestaShop installation
	require('../../config/config.inc.php');


	// Change this to match your database requirements.
	dbExt::connect('mysql:host=localhost;dbname=yourdb', 'youruser', 'yourpassword');
	//
	$db = dbExt::getInstance();

	// Fill in the path to jQuery
	$jquery_path = 'static/';
	// Fill in the path to jQuery DataTables
	$datatables_path ='static/';
	// Fill in the path to jQuery DataTabless' CSS file
	$datatables_css_path = 'static/';

	// Nothing else needs editing.


	// ----------------------------------- Edit below (or indeed above) this line at your own risk.


	// Invoked by ajax call
	if (!empty($_REQUEST['update']))
	{
		$id = intval($_REQUEST['update']);
		$table = $_REQUEST['table'];
		$column = $_REQUEST['col'];
		$val = $_REQUEST['val'];

		if ($table == 'category')
		{
			// Delete the product from the original category (or categories) and add it to the new one.
			$db->run('DELETE FROM ps_category_product WHERE id_product = ?', $id);
			$db->insert('ps_category_product', array('id_product' => $id, 'id_category' => $val));

			// Invalidate cache
			$prod = new Product($id);

			Hook::exec('actionProductUpdate', array('product' => $prod));

			if (in_array($prod->visibility, array('both', 'search')) && Configuration::get('PS_SEARCH_INDEXATION'))
				Search::indexation(false, $prod->id);

		} elseif ($table == 'delete')
		{
			// Totally removes a product
			$db->run('DELETE FROM ps_category_product WHERE id_product = ?', $id);
			$db->run('DELETE FROM ps_product WHERE id_product = ?', $id);
			$db->run('DELETE FROM ps_product_lang WHERE id_product = ?', $id);
		} elseif ($table == 'image')
		{
			// Changes a product's image
			$product = new Product($id);
			$product->deleteImages();

			$image = new Image();
			$image->id_product = $id;
			$image->position = 1;
			$image->cover = true;
			foreach (Language::getLanguages() as $lang)
				$image->legend[$lang['id_lang']] = '';
			$image->add();

			$tmpName = tempnam(_PS_IMG_DIR_, 'PS');
			$ifp = fopen($tmpName, "wb");
			$data = explode(',', $val);
			fwrite($ifp, base64_decode($data[1]));
			fclose($ifp);

			$new_path = $image->getPathForCreation();
			ImageManager::resize($tmpName, $new_path.'.'.$image->image_format);

			$imagesTypes = ImageType::getImagesTypes('products');
			foreach ($imagesTypes as $imageType)
    			ImageManager::resize($tmpName, $new_path.'-'.stripslashes($imageType['name']).'.'.$image->image_format, $imageType['width'], $imageType['height'], $image->image_format);

			$image_url = _PS_BASE_URL_._THEME_PROD_DIR_.$image->getExistingImgPath()."-small_default.jpg";

			// Outputs an image tag for inline replacement
			die("<img src='$image_url'>");
		}
		elseif ($table == 'active') {
			// Activates or deactivates a product
			$prod = new Product($id);
			if ($val == '1')
			{
				$prod->visibility = 'both';
				$prod->indexed = 1;
				$prod->active = 1;
				$prod->update();
			}
			else
			{
				$prod->visibility = 'none';
				$prod->indexed = 0;
				$prod->active = 0;
				$prod->update();
			}

			// Invalidate cache
			Hook::exec('actionProductUpdate', array('product' => $prod));

			if (in_array($prod->visibility, array('both', 'search')) && Configuration::get('PS_SEARCH_INDEXATION'))
				Search::indexation(false, $prod->id);

		}else {
			// Generic column update
			$db->update($table, array($column => $val), "id_product = {$id}");
		}

		// Also update ps_product_shop for defined columns
		if ($column == 'price' || $column == 'wholesale_price') {
			$db->update('ps_product_shop', array($column => $val), "id_product = {$id}");
		}

		// Halt execution.
		die("OK");
	}

	// Fetch a list of all categories for the select element
	$categories = $db->run("SELECT id_category, name FROM ps_category_lang WHERE id_lang = 1 ORDER BY name ASC");

	// Fetch every product. This may take some time, could be optimised by only loading the current page.
	$products = $db->run("
		SELECT id_product, ps_product_lang.name AS product_name, ps_category_lang.name AS category_name, ps_product_lang.description AS description, reference, wholesale_price, price, ps_product.active AS active
		FROM ps_product
		JOIN ps_product_lang USING (id_product)
		LEFT JOIN ps_category_product USING (id_product)
		LEFT JOIN ps_category_lang USING (id_category)

		GROUP BY id_product
	");
?>

<!doctype html>
<html>
	<head>
		<script type="text/javascript" language="javascript" src="<?php echo($jquery_path); ?>"></script>
		<script type="text/javascript" language="javascript" src="<?php echo($datatables_path); ?>"></script>

		<style type='text/css'>
			@import "<?php echo($datatables_css_path); ?>";
		</style>

		<style type='text/css'>
			td
			{
				height:48px;
			}

			td input, td select
			{
				width:100%;
			}

			td[data-rel='description']
			{
				font-size: 12px;
			}

			.dataTables_info
			{
				position:fixed;
				bottom:8px;
				left:8px;
				background-color:white;
				padding:4px;
			}

			.dataTables_paginate
			{
				position:fixed;
				bottom:8px;
				right:8px;
				background-color:white;
				padding:4px;
			}

			table.dtable
			{
				margin-bottom:32px;
			}

			.load
			{
				position:fixed;
				width:100%;
				height:100%;
				background-color:white;
				background-image:url(static/loadmain.gif);
				background-repeat:no-repeat;
				background-position:center center;

				font-size:32px;
				text-align:center;
			}
		</style>

		<script type='text/javascript'>
			$(window).load(function() {
				$(".load").remove();
			});

			$(function() {
				var table = $(".dtable").dataTable({
					'fnRowCallback': function(nRow, aData, iDisplayIndex) {
						var img = $('img[rel]', nRow);
						img.attr('src', img.attr('rel'));
						return nRow;
					},
					'sPaginationType': 'full_numbers'
				});

				$("td[data-rel='active'] input").live('click', function() {
					var checked = $(this).prop('checked');
					var ele = $(this).parent();
					var input = $(this);

					var val = checked ? '1' : '0';
					var id = ele.parent().data('id');
					var table = ele.data('table');
					var col = ele.data('rel');

					$.ajax({
						type: 'POST',
						url: 'bulkprod.php',
						data: {
							update: id,
							table: table,
							col: col,
							val: val
						}
					}).done(function() {
						input.prop('checked', checked);
					});
				});

				$("td[data-table]").live('click', function() {
					if ($(this).has('input, select').length>0)
					{
						return false;
					}

					var ele = $(this);
					var text = ele.text();
					var input = false;

					if (ele.data('rel')=='category')
					{
						input = $("<select>");

						<?php foreach($categories as $cat): ?>
							var opt = $("<option value='<?=$cat["id_category"]?>'><?=$cat["name"]?></option>");
							input.append(opt);

							if (opt.text() == text)
								opt.attr('selected', 'selected');
						<?php endforeach; ?>

						input.blur(function() {
							ele.html('Saving...');

							var text = $(this).find('option:selected').text();
							var val = $(this).val();
							var id = ele.parent().data('id');
							var table = ele.data('table');
							var col = ele.data('rel');

							$.ajax({
								type: 'POST',
								url: 'bulkprod.php',
								data: {
									update: id,
									table: table,
									col: col,
									val: val
								}
							}).done(function() {
								ele.html(text);
							});

							$(this).remove();
						});

						ele.html('');

					}
					else if (ele.data('rel')=='delete')
					{
						var id = ele.parent().data('id');

						ele.html('');
						input = $("<input type='submit' value='Delete FOREVER'>");

						input.click(function() {
							ele.parent().fadeOut();

							$.ajax({
							type: 'POST',
							url: 'bulkprod.php',
							data: {
								update: id,
								table: 'delete',
								col: 'delete',
								val: 1
							}
							}).done(function(data) {
								ele.parent().remove();
							});
						});


					}
					else if (ele.data('rel')=='image')
					{
						input = $("<input type='file' style='display:none'>");

						input.change(function() {
							ele.find('img').attr('src', 'static/upload.gif');

							var fileReader= new FileReader();
							var file = $(this)[0].files[0];

							fileReader.onload = function(event)
							{
								var result = event.target.result;
								var id = ele.parent().data('id');

								$.ajax({
									type: 'POST',
									url: 'bulkprod.php',
									data: {
										update: id,
										table: 'image',
										col: 'image',
										val: result
									}
								}).done(function(data) {
									input.remove()
									ele.html(data);
								});
							}

							fileReader.readAsDataURL(file);
						});
						input.click();
					}
					else
					{
						input = $("<input type='text'>");
						input.val(text);

						input.blur(function() {
							if ($(this).val().length>0)
							{
								ele.html('Saving...');

								var val = $(this).val();
								var id = ele.parent().data('id');
								var table = ele.data('table');
								var col = ele.data('rel');

								$.ajax({
									type: 'POST',
									url: 'bulkprod.php',
									data: {
										update: id,
										table: table,
										col: col,
										val: val
									}
								}).done(function() {
									ele.html(val);
								});
							}
							else
								ele.html(text);

							$(this).remove();
						});

						ele.html('');
					}
					ele.append(input);

					input.focus();
				});
			});
		</script>
	</head>

	<body>
		<div class='load'>Loading products...</div>
		<h1>Bulk Product Editor</h1>

		<table class='dtable'>
			<thead>
				<tr>
					<th>ID</th>
					<th>Image</th>
					<th>Name</th>
					<th>Reference</th>
					<th>Category</th>
					<th>Description</th>
					<th>Wholesale Price</th>
					<th>Retail Price</th>
					<th>Enabled</th>
					<th>Delete</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($products as $row): ?>
					<tr data-id='<?=$row['id_product'] ?>'>
						<td><?=$row['id_product'] ?></td>
						<td data-rel='image' data-table='image'>
							<?php
								// Find the PrestaShop image path
								$image = Image::getCover($row['id_product']);
								$image = new Image($image['id_image']);
								$image_url = _PS_BASE_URL_._THEME_PROD_DIR_.$image->getExistingImgPath()."-small_default.jpg";
								if (!empty($image))
									echo("<img rel='$image_url'>");
							?>
						</td>
						<td data-rel='name' data-table='ps_product_lang'><?=$row['product_name'] ?></td>
						<td data-rel='reference' data-table='ps_product'><?=$row['reference'] ?></td>
						<td data-rel='category' data-table='category'><?=$row['category_name'] ?></td>
						<td data-rel='description' data-table='ps_product_lang'><?=$row['description'] ?></td>
						<td data-rel='wholesale_price' data-table='ps_product'><?=$row['wholesale_price'] ?></td>
						<td data-rel='price' data-table='ps_product'><?=$row['price'] ?></td>
						<td data-rel='active' data-table='active'><span style='display:none'><?=$row['active']; ?></span><input type='checkbox' <?=$row['active']=='1' ? 'checked' : '' ?>></td>
						<td data-rel='delete' data-table='delete'>Delete</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</body>
</html>