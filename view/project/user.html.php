<?php include 'view/project/header.html.php' ?>
<?php use Goteo\Library\Text; ?>
USUARIO / Perfil<br />
GUÍA: <?php echo $guideText;  ?><br />
<?php include 'view/project/errors.html.php' ?>
<hr />
<form action="/project/user" method="post">
	<dl>
		<dt><label for="name">Nombre completo</label></dt>
		<dd><input type="text" id="name" name="name" value="<?php echo $user->name; ?>"/></dd>
		<span><?php echo Text::get('tooltip user name'); ?></span><br />

		<dt><label for="image">Tu imagen</label></dt>
		<dd><input type="file" id="theimage" name="theimage" value=""/> img src="<?php echo $user->avatar; ?>" </dd>
		<input type="text" name="image" value="avatar.jpg" />
		<span><?php echo Text::get('tooltip user image'); ?></span><br />

		<dt><label for="about">Cu&eacute;ntanos algo sobre t&iacute;</label></dt>
		<dd><textarea id="about" name="about" cols="100" rows="10"><?php echo $user->about; ?></textarea></dd>
		<span><?php echo Text::get('tooltip user about'); ?></span><br />

		<dt><label for="interests">Intereses</label></dt>
		<dd><select id="interests" name="interests">
				<?php foreach ($interests as $Id=>$Val) : ?>
				<option value="<?php echo $Id; ?>" <?php if ($user->interests == $Id) echo ' selected="selected"'; ?>><?php echo $Val; ?></option>
				<?php endforeach; ?>
			</select></dd>
		<span><?php echo Text::get('tooltip user interests'); ?></span><br />

		<dt><label for="keywords">Palabras clave</label></dt>
		<dd>Añadir:<input type="text" id="keywords" name="keywords" value=""/>(separadas por comas)</dd>
		<input type="button" id="new-keyword" value="Nuevo" />
		<div><?php foreach ($project->keywords as $keyword) : ?>
			<label>(X)<input type="checkbox" name="remove-keyword<?php echo $keyword->id; ?>" value="<?php echo $keyword->id; ?>" /></label>
			<span><?php echo $keyword->keyword; ?></span>
		<?php endforeach; ?></div>
		<span><?php echo Text::get('tooltip user keywords'); ?></span><br />

		<dt><label for="contribution">Qué podrías aportar a Goteo</label></dt>
		<dd><textarea id="contribution" name="contribution" cols="100" rows="10"><?php echo $user->contribution; ?></textarea></dd>
		<span><?php echo Text::get('tooltip user contribution'); ?></span><br />

		<dt><label for="blog">Blog</label></dt>
		<dd>http://<input type="text" id="blog" name="blog" value="<?php echo $user->blog; ?>"/></dd>
		<span><?php echo Text::get('tooltip user blog'); ?></span><br />

		<dt><label for="twitter">Twitter</label></dt>
		<dd>http://twitter.com/<input type="text" id="twitter" name="twitter" value="<?php echo $user->twitter; ?>"/></dd>
		<span><?php echo Text::get('tooltip user twitter'); ?></span><br />

		<dt><label for="facebook">Facebook</label></dt>
		<dd>http://facebook.com/<input type="text" id="facebook" name="facebook" value="<?php echo $user->facebook; ?>"/></dd>
		<span><?php echo Text::get('tooltip user facebook'); ?></span><br />

		<dt><label for="linkedin">Linkedin</label></dt>
		<dd>http://linkedin.com/<input type="text" id="linkedin" name="linkedin" value="<?php echo $user->linkedin; ?>"/></dd>
		<span><?php echo Text::get('tooltip user linkedin'); ?></span><br />

	</dl>
	<input type="submit" name="submit" value="CONTINUAR" />
</form>
<?php include 'view/project/footer.html.php' ?>