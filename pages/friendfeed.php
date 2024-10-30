<?php
if(!function_exists('metamatic_is_admin')) {
    return;
}
$admin = metamatic_is_admin();
if($admin) {
    if(@$_POST['loadFeeds']) {
        metamatic_feed_task();
    }
}
$items = metamatic_db_query('SELECT i.title post_title, i.content post_content, i.url post_url, a.value, a.title FROM {metamatic_feed_items} i, {metamatic_assets} a WHERE i.aid=a.aid ORDER BY i.time_imported DESC LIMIT 25');

?>
<div class="metamatic">
    <?php if($admin) { ?>
    <form method="POST" action="">
        <input type="hidden" name="loadFeeds" value="1"/>
        <button type="submit"><img alt="*" src="<?php echo metamatic_get_style_dir() ?>/refresh.png"> <?php metamatic_e('Refresh friends feed') ?></button>
    </form>
    <?php } ?>

    <?php foreach($items as $item) { ?>
        <h3><?php if($item->post_url) { ?><a href="<?php echo $item->post_url ?>"><?php } ?><?php echo $item->post_title ?><?php if($item->post_url) { ?></a><?php } ?></h3>
        <?php echo Asset::getLink($item) ?>

        <p><?php echo $item->post_content ?></p>
        <hr/>
    <?php } ?>

    <?php metamatic_logo() ?>
</div>