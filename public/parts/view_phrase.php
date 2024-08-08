<?php
if(!isset($view_phrase)){
    exit;
}
?>
<h1><?php echo $view_phrase['phrase']; ?></h1>
<hr>
<h2>Spanish</h2>
<p><?php echo $view_phrase['spanish']; ?></p>
<h2>German</h2>
<p><?php echo $view_phrase['german']; ?></p>
<h2>Italian</h2>
<p><?php echo $view_phrase['italian']; ?></p>
<h2>French</h2>
<p><?php echo $view_phrase['french']; ?></p>
<h2>Portuguese</h2>
<p><?php echo $view_phrase['portuguese']; ?></p>
<h2>Norwegian</h2>
<p><?php echo $view_phrase['norwegian']; ?></p>
<hr>
<p class="flex-justify-center"><a class="button" href="/">Receive Daily Phrase via email, for free!</a></p>
