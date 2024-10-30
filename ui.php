<?php

function metamatic_get_asset_list($params = array()) {
    $limit = '';
    if (isset($params['limit'])) {
        $limit = ' LIMIT ' . (int) $params['limit'];
    }

    $order = '';
    if (isset($params['order'])) {
        $order = ' ORDER BY ' . $params['order'];
    }

    $sql = "SELECT * FROM {metamatic_assets}$order $limit";
    $assets = metamatic_db_query($sql);
    return $assets;
}

function metamatic_get_total_asset_count() {
    $assets = metamatic_db_query_row('SELECT COUNT(*) c FROM {metamatic_assets}');
    return $assets->c;
}

function metamatic_favorites_body() {
    $assets = metamatic_get_asset_list(array(
            'order' => 'RAND()',
            'limit' => 20,
        ));
    $total = metamatic_get_total_asset_count();
?>
<div class="metamatic">
    <ul class="assets">
    <?php
    foreach ($assets as $ass) {
    ?>
        <li><?php echo Asset::getLink($ass) ?></li>
    <?php
    }
    if (count($assets) < $total) {
    ?><li><a href="<?php echo metamatic_get_page_url('favorites') ?>"><?php printf(metamatic_t('...%d more'), $total - count($assets)) ?></a></li> <?php
    }
    ?></ul>
<a title="<?php metamatic_e('See all my favorite sites') ?>" href="<?php echo metamatic_get_page_url('favorites') ?>"><?php printf(metamatic_t('%d friends total'), $total) ?></a>
&nbsp; <a title="<?php metamatic_e('Read posts from my favorite sites') ?>" href="<?php echo metamatic_get_page_url('friendfeed') ?>"><?php metamatic_e('Friends feed') ?></a>
&nbsp; <?php metamatic_logo('small') ?>
</div>
<?php
}