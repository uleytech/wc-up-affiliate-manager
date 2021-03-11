<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

function upAffiliateManagerAction()
{
    if (isset($_POST['import'])) {
        return upAffiliateManagerImportProducts();
    } elseif (isset($_POST['delete_categories'])) {
        return upAffiliateManagerImportProductDeleteCategories();
    } elseif (isset($_POST['delete_attributes'])) {
        return upAffiliateManagerImportProductDeleteAttributes();
    }
}

/**
 * @return string
 */
function upAffiliateManagerImportProducts(): string
{
    $options = get_option(UP_AFFILIATE_MANAGER_OPTIONS);

    $client = new Client();
    $token = [
        'token' => $options['token'],
    ];
    $parameters = http_build_query($token);
    try {
        $response = $client->get(UP_AFFILIATE_MANAGER_API_URL . '/rss/' . $options['language'] . '?' . $parameters);
    } catch (GuzzleException $exception) {
        echo $exception->getMessage();
    }
    $rawProducts = json_decode($response->getBody(), true);
    $products = [];
    foreach ($rawProducts as $product) {
        $products[$product['group_id']][] = $product;
    }
    $imported = [];
    $updated = [];
    $deleted = [];
    $isIncludeGroups = false;
    foreach ($products as $group) {
        if (isset($options['include_groups']) && $options['include_groups'] !== '') {
            $isIncludeGroups = true;
            $includeGroups = explode(',', $options['include_groups']);
            if (!in_array($group[0]['group_id'], $includeGroups)) {
                continue;
            }
        }
        if (isset($options['exclude_groups']) && $options['exclude_groups'] !== '') {
            $excludeGroups = explode(',', $options['exclude_groups']);
            if (in_array($group[0]['group_id'], $excludeGroups)) {
                continue;
            }
        }

        $product = upAffiliateManagerGetProductBySku($group[0]['group_id']);
        if ($product) {
            $skus = [];
            $attributes = setProductAttributes($group);
            $product->set_attributes($attributes);
            // update
            foreach ($group as $item) {
                $skus[] = (string)$item['product_id'];
                $productVariation = getProductVariationBySku($item['product_id']);
                if ($productVariation) {
                    // update
                    updateProductVariation($productVariation, $item);
                } else {
                    // add
                    try {
                        addProductVariation($product, $item);
                    } catch (WC_Data_Exception $exception) {
                        return $exception->getMessage();
                    }
                }
            }
            $product->set_stock_status();
//            $product->set_category_ids(
//                getCategoryByName($group[0]['category_name'], $group[0]['category_seo_description'])
//            );
            $imageId = $product->get_image_id();
            if (!$imageId) {
                $imageId = upAffiliateManagerGetIdFromPictureUrl($group[0]['image']);
                $product->set_image_id($imageId);
            }
            $productId = $product->save();

            // delete
            $productVariations = $product->get_children();
            $skusOnShop = [];
            foreach ($productVariations as $productVariationId) {
                $productVariation = wc_get_product($productVariationId);
                $skusOnShop[] = (string)$productVariation->get_sku();
            }
            $skusNotInStock = array_diff($skusOnShop, $skus);
            foreach ($skusNotInStock as $skuNotInStock) {
                $productVariation = getProductVariationBySku($skuNotInStock);
                if ($productVariation) {
                    $productVariation->set_stock_status('outofstock');
                    $productVariation->save();
                }
            }
            $updated[] = $productId;
        } else {
            // add
            $product = new WC_Product_Variable();
            try {
                $product->set_name($group[0]['group_name']);
                $product->set_description($group[0]['product_seo_description'] ?? '');
                $product->set_short_description($group[0]['product_description'] ?? '');
                $product->set_sku($group[0]['group_id']);
                $product->set_category_ids(
                    getCategoryByName($group[0]['category_name'], $group[0]['category_seo_description'])
                );
                $product->set_reviews_allowed(false);
                $product->set_status('publish');
                $product->set_stock_status();
//            $product->set_gallery_image_ids([$imageId]);
            } catch (WC_Data_Exception $exception) {
                return $exception->getMessage();
            }

            $attributes = setProductAttributes($group);
            $product->set_attributes($attributes);
            $productId = $product->save();
            $imageId = upAffiliateManagerGetIdFromPictureUrl($group[0]['image']);
            if ($imageId) {
                $product->set_image_id($imageId);
            }
            $product->save();

            foreach ($group as $item) {
                // add
                try {
                    addProductVariation($product, $item);
                } catch (WC_Data_Exception $exception) {
                    return $exception->getMessage();
                }
            }
            $imported[] = $productId;
        }
    }

    if (!$isIncludeGroups) {
        $productsNotInStock = array_diff(getProductIds(), $imported, $updated);
        foreach ($productsNotInStock as $productNotInStock) {
            $product = wc_get_product($productNotInStock);
            if ($product) {
                $product->set_stock_status('outofstock');
                $product->save();
                $productVariations = $product->get_children();
                foreach ($productVariations as $productVariationId) {
                    $productVariation = wc_get_product($productVariationId);
                    $productVariation->set_stock_status('outofstock');
                    $productVariation->save();
                }
                $deleted[] = $product->get_id();
            }
        }
    }

    upAffiliateManagerUpdateOptions();

    return esc_html__('All products import successful', UP_AFFILIATE_MANAGER_PROJECT) . ', '
        . count($imported) . ' ' . esc_html__('imported') . ', '
        . count($updated) . ' ' . esc_html__('updated') . ', '
        . count($deleted) . ' ' . esc_html__('out of stock');
}

function upAffiliateManagerImportProductDeleteCategories()
{
    global $wpdb;
    $wpdb->query("
        DELETE a, c 
        FROM {$wpdb->base_prefix}terms AS a
        LEFT JOIN {$wpdb->base_prefix}term_taxonomy AS c ON a.term_id = c.term_id
        LEFT JOIN {$wpdb->base_prefix}term_relationships AS b ON b.term_taxonomy_id = c.term_taxonomy_id
        WHERE c.taxonomy = 'product_cat'
        AND a.slug not like 'uncategorized'
    ");

    return esc_html__('Affected', UP_AFFILIATE_MANAGER_PROJECT) . ': ' . $wpdb->rows_affected;
}

function upAffiliateManagerImportProductDeleteAttributes()
{
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->base_prefix}woocommerce_attribute_taxonomies");
    $wpdb->query("DELETE FROM {$wpdb->base_prefix}options where option_name = '_transient_wc_attribute_taxonomies' limit 1");
    $wpdb->query("
        DELETE a, c, b  
        FROM {$wpdb->base_prefix}terms AS a
        LEFT JOIN {$wpdb->base_prefix}term_taxonomy AS c ON a.term_id = c.term_id
        LEFT JOIN {$wpdb->base_prefix}termmeta AS b ON b.term_id = a.term_id
        WHERE c.taxonomy like 'pa_%'
    ");

    return esc_html__('Affected', UP_AFFILIATE_MANAGER_PROJECT) . ': ' . $wpdb->rows_affected;
}

/**
 * @param string $url
 * @return int|null
 */
function upAffiliateManagerGetIdFromPictureUrl(string $url): ?int
{
    global $wpdb;
    $fileName = basename($url, '.jpg');

    $sql = $wpdb->prepare(
        "SELECT post_id FROM $wpdb->postmeta 
         WHERE meta_key = '_wp_attached_file' AND meta_value like '%s'",
        '%/' . $fileName
    );
    $postId = $wpdb->get_var($sql);

    if (!$postId) {
        $size = getimagesize($url);
        if (!$size) {
            return null;
        }
        $postId = media_sideload_image(
            $url,
            null,
            $fileName,
            'id'
        );
    }

    return (!($postId instanceof WP_Error)) ? $postId : null;
}

/**
 * @param string $data
 * @return string
 */
function upAffiliateManagerSanitizer(string $data): string
{
    return strtolower(str_replace(['(', ')', '%', ' '], '', $data));
}

/**
 * @param string $sku
 * @return WC_Product_Variable|null
 */
function upAffiliateManagerGetProductBySku(string $sku): ?WC_Product_Variable
{
    global $wpdb;
    $product_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1",
            $sku
        )
    );
    if ($product_id) {
        return new WC_Product_Variable($product_id);
    }
    return null;
}

/**
 * @return array|null
 */
function getProductIds(): ?array
{
    global $wpdb;
    $rawProducts = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id FROM $wpdb->posts WHERE post_type='%s'",
            'product'
        ), 'ARRAY_A'
    );

    return
        array_map(
            function ($item) {
                return $item['id'];
            },
            $rawProducts
        );
}

/**
 * @param string $sku
 * @return WC_Product_Variation|null
 */
function getProductVariationBySku(string $sku): ?WC_Product_Variation
{
    global $wpdb;
    $product_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1",
            $sku
        )
    );
    if ($product_id) {
        return new WC_Product_Variation($product_id);
    }
    return null;
}

/**
 * @param string $name
 * @param string $description
 * @return array
 */
function getCategoryByName(string $name, string $description): array
{
    if (!term_exists($name, 'product_cat')) {
        return wp_insert_term(
            $name, // the term
            'product_cat', // the taxonomy
            [
                'description' => $description,
                'slug' => '',
            ]
        );
    } else {
        return (get_term_by('name', $name, 'product_cat'))->to_array();
    }
}

/**
 * @param WC_Product_Variable $product
 * @param array $data
 * @return null|int
 * @throws WC_Data_Exception
 */
function addProductVariation(WC_Product_Variable $product, array $data): ?int
{
    $options = get_option(UP_AFFILIATE_MANAGER_OPTIONS);
    $attributes = [];
    $excludeAttributes = [];
    if (isset($options['exclude_attributes']) && $options['exclude_attributes'] !== '') {
        $excludeAttributes = explode(',', $options['exclude_attributes']);
    }
    $variation = new WC_Product_Variation();
    $variation->set_regular_price($data['product_price']);
    try {
        $variation->set_sku($data['product_id']);
    } catch (WC_Data_Exception $exception) {
        throw $exception;
    }
    $variation->set_parent_id($product->get_id());
    $variation->set_status('publish');
    $variation->set_stock_status();

    $productDosageType = upAffiliateManagerSanitizer($data['product_dosage_type']);
    if (in_array($productDosageType, $excludeAttributes)) {
        return null;
    }
    $attributes[$productDosageType] = trim($data['product_dosage']);

    $productPackageType = upAffiliateManagerSanitizer($data['product_package_type']);
    if (in_array($productPackageType, $excludeAttributes)) {
        return null;
    }
    $attributes[$productPackageType] = trim($data['product_package']);

    if (count($attributes) > 0) {
        $variation->set_attributes($attributes);
    }

    return $variation->save();
}

/**
 * @param WC_Product_Variation $productVariation
 * @param array $data
 * @return void
 */
function updateProductVariation(WC_Product_Variation $productVariation, array $data): void
{
    $options = get_option(UP_AFFILIATE_MANAGER_OPTIONS);
    $attributes = [];
    $excludeAttributes = [];
    if (isset($options['exclude_attributes']) && $options['exclude_attributes'] !== '') {
        $excludeAttributes = explode(',', $options['exclude_attributes']);
    }

    $productVariation->set_stock_status();

    if ($productVariation->get_regular_price() !== $data['product_price']) {
        $productVariation->set_regular_price($data['product_price']);
    }
    $productDosage = $productVariation->get_attribute('product_dosage_type');
    $productDosageType = upAffiliateManagerSanitizer($data['product_dosage_type']);
    if (!in_array($productDosageType, $excludeAttributes) && $productDosage !== $data['product_dosage']) {
        $attributes[$productDosageType] = trim($data['product_dosage']);
    }
    $productPackage = $productVariation->get_attribute('product_package_type');
    $productPackageType = upAffiliateManagerSanitizer($data['product_package_type']);
    if (!in_array($productPackageType, $excludeAttributes) && $productPackage !== $data['product_package']) {
        $attributes[$productPackageType] = trim($data['product_package']);
    }
    if (count($attributes) > 0) {
        $productVariation->set_attributes($attributes);
    }
    $productVariation->save();
}


/**
 * @param array $data
 * @return array
 */
function addProductAttributes(array $data): array
{
    $attributes = [];
    foreach ($data as $type => $dosage) {
        $dosage = array_unique($dosage);
        $attribute = new WC_Product_Attribute();
        $attribute->set_id(0);
        $attribute->set_name($type);
        $attribute->set_options(array_values($dosage));
        $attribute->set_visible(true);
        $attribute->set_variation(true);
        $attributes[] = $attribute;
    }
    return $attributes;
}

/**
 * @param array $group
 * @return array
 */
function setProductAttributes(array $group): array
{
    $options = get_option(UP_AFFILIATE_MANAGER_OPTIONS);
    $dosages = [];
    $packages = [];
    $excludeAttributes = [];
    if (isset($options['exclude_attributes']) && $options['exclude_attributes'] !== '') {
        $excludeAttributes = explode(',', $options['exclude_attributes']);
    }

    foreach ($group as $item) {
        $productDosageType = upAffiliateManagerSanitizer($item['product_dosage_type']);
        if (!in_array($productDosageType, $excludeAttributes)) {
            $dosages[$productDosageType][] = trim($item['product_dosage']);
        }
        $productPackageType = upAffiliateManagerSanitizer($item['product_package_type']);
        if (!in_array($productPackageType, $excludeAttributes)) {
            $packages[$productPackageType][] = trim($item['product_package']);
        }
    }
    return array_merge(
        addProductAttributes($dosages),
        addProductAttributes($packages)
    );
}