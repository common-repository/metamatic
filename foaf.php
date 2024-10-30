<?php
require_once(dirname(__FILE__) . '/../../../wp-load.php');
if(!function_exists('metamatic_activate')) {
    return;
}
require_once(dirname(__FILE__) . '/metamatic-wp.php');
require_once(dirname(__FILE__) . '/client.php');
require_once(dirname(__FILE__) . '/feeds.php');

$interests = metamatic_split_words_csv(metamatic_get_interests());
$favorites = metamatic_get_asset_list();

header('Content-Type: application/rdf+xml');

?><?php echo '<' ?>?xml version="1.0" encoding="utf-8"?>
<rdf:RDF
   xml:lang="en"
   xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
   xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
   xmlns:foaf="http://xmlns.com/foaf/0.1/"
   xmlns:ya="http://blogs.yandex.ru/schema/foaf/"
   xmlns:lj="http://www.livejournal.org/rss/lj/1.0/"
   xmlns:geo="http://www.w3.org/2003/01/geo/wgs84_pos#"
   xmlns:dc="http://purl.org/dc/elements/1.1/" 
   xmlns:mm="http://metamatic.net/schema/foaf/">
  <foaf:Person>
    <foaf:nick><?php echo metamatic_esc(get_bloginfo('name')) ?></foaf:nick>
    <foaf:name><?php echo metamatic_esc(get_bloginfo('name')) ?></foaf:name>
    <lj:journaltitle><?php echo metamatic_esc(get_bloginfo('name')) ?></lj:journaltitle>
    <lj:journalsubtitle><?php echo metamatic_esc(get_bloginfo('description')) ?></lj:journalsubtitle>
    <foaf:weblog rdf:resource="<?php echo get_bloginfo('url') ?>" />
    <foaf:homepage rdf:resource="<?php echo get_bloginfo('url') ?>" dc:title="<?php echo metamatic_esc_attr(get_bloginfo('name')) ?>" />
<?php foreach($interests as $interest) { ?>
    <foaf:interest dc:title="<?php echo metamatic_esc_attr($interest) ?>" />
<?php } ?>
<?php foreach($favorites as $ass) {
    Asset::prepare($ass);
    $encoded = Asset::encode($ass);
    ?>
<foaf:knows><foaf:Person><foaf:nick><?php echo metamatic_esc($ass->title) ?></foaf:nick><foaf:weblog rdf:resource="<?php echo metamatic_esc_attr($ass->url) ?>"/><mm:asset><?php echo metamatic_esc($encoded) ?></mm:asset></foaf:Person></foaf:knows>
<?php } ?>
  </foaf:Person>
</rdf:RDF>