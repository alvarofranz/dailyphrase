<div class="flex-me-down">
    <h1>Receive a daily phrase in multiple languages</h1>
    <p>It will take you 30 seconds each day to read the phrases and it will help you get new vocabulary as well
        as
        stay in touch with the languages you love.</p>
    <p>Just write your email below, pick your languages, and start receiving your daily dose of language practice for
        free!</p>
    <form method="post" action="/" class="flex-me-down">
        <label><input type="email" id="email" name="email" placeholder="your-email@example.com" required></label>
        <div class="languages-picker">
            <label><input type="checkbox" name="spanish"> Spanish</label>
            <label><input type="checkbox" name="german"> German</label>
            <label><input type="checkbox" name="italian"> Italian</label>
            <label><input type="checkbox" name="french"> French</label>
            <label><input type="checkbox" name="portuguese"> Portuguese</label>
            <label><input type="checkbox" name="norwegian"> Norwegian</label>
        </div>
        <div class="g-recaptcha" data-sitekey="<?php echo $_ENV['RECAPTCHA_SITE_KEY']; ?>"></div>
        <button class="button" type="submit">Subscribe</button>
    </form>
    <footer>
        dailyphrase.email - <a target="_blank" href="/privacy.txt" title="Privacy policy">Privacy Policy</a>
    </footer>
</div>