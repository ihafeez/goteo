<?php 

use Goteo\Core\View,
    Goteo\Library\Text;

$calls     = $this['calls'];
$campaigns = $this['campaigns'];

$bodyClass = 'home';
// para que el prologue ponga el código js para botón facebook en el bannerside
$fbCode = Text::widget(Text::get('social-account-facebook'), 'fb');
include 'view/prologue.html.php';
include 'view/header.html.php';
?>
<script type="text/javascript">
    $(function(){
        $('#sub-header').slides();
    });

</script>
<div id="sub-header" class="banners">
    <div class="clearfix">
        <div class="slides_container">
            <!-- Módulo de texto más sign in -->
            <div class="subhead-banner"><?php echo Text::html('main-banner-header'); ?></div>
            <!-- Módulo banner imagen más resumen proyecto -->
            <?php if (!empty($this['banners'])) : foreach ($this['banners'] as $id=>$banner) : ?>
            <div class="subhead-banner"><?php echo new View('view/header/banner.html.php', array('banner'=>$banner)); ?></div>
            <?php endforeach;
            else : ?>
            <div class="subhead-banner"><?php echo Text::html('main-banner-header'); ?></div>
            <?php endif; ?>
        </div>
        <div class="mod-pojctopen"><?php echo Text::html('open-banner-header', $fbCode); ?></div>
    </div>
    <div class="sliderbanners-ctrl">
        <a class="prev">prev</a>
        <ul class="paginacion"></ul>
        <a class="next">next</a>
    </div>
</div>
<div id="main">

    <?php foreach ($this['order'] as $item=>$itemData) {
        if (!empty($this[$item])) echo new View("view/home/{$item}.html.php", $this);
    } ?>

    <p>
        <a class="node-jump" href="http://test.devgoteo.org/">A TestNode</a>
    </p>
    <p>
        <a class="node-jump" href="http://nodo.devgoteo.org/">A Nodo</a>
    </p>

</div>
<?php include 'view/footer.html.php'; ?>
<?php include 'view/epilogue.html.php'; ?>