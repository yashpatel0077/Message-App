<section class="profile-editor-card">
  <header class="profile-editor-head">
    <div>
      <h2><?php echo esc($profileEditorTitle ?? 'Profile'); ?></h2>
      <p><?php echo esc($profileEditorDescription ?? 'Update your name, username, about, and profile photo.'); ?></p>
    </div>
    <div class="profile-editor-avatar" id="profileEditorAvatar">
      <?php if (!empty($profileAvatar)): ?><img src="<?php echo esc($profileAvatar); ?>" alt="Current profile photo"><?php else: ?><?php echo esc($profileInitial); ?><?php endif; ?>
    </div>
  </header>
  <form class="profile-editor-form" method="post" action="<?php echo esc($profileFormAction); ?>" enctype="multipart/form-data" autocomplete="off">
    <?php if (!empty($profileErrors)): ?><div class="profile-editor-errors" role="alert"><?php foreach ($profileErrors as $profileError): ?><div><?php echo esc($profileError); ?></div><?php endforeach; ?></div><?php endif; ?>
    <div class="profile-editor-grid">
      <div class="profile-editor-field">
        <label for="profile_name">Profile name</label>
        <input id="profile_name" name="profile_name" type="text" maxlength="100" value="<?php echo esc($profileNameValue); ?>" autocomplete="name" required>
      </div>
      <div class="profile-editor-field">
        <label for="username">Username</label>
        <input id="username" name="username" type="text" minlength="3" maxlength="30" pattern="[a-zA-Z0-9._]{3,30}" value="<?php echo esc($profileUsernameValue); ?>" autocomplete="username" required>
        <p class="profile-editor-hint">3–30 characters: letters, numbers, dots, or underscores.</p>
      </div>
    </div>
    <div class="profile-editor-field profile-editor-about">
      <label for="about">About</label>
      <textarea id="about" name="about" maxlength="220" placeholder="Write something about yourself."><?php echo esc($profileAboutValue); ?></textarea>
      <p class="profile-editor-hint">Shown on your profile. Maximum 220 characters.</p>
    </div>
    <div class="profile-editor-upload">
      <div class="profile-editor-avatar profile-editor-avatar-large" id="profileEditorUploadAvatar">
        <?php if (!empty($profileAvatar)): ?><img src="<?php echo esc($profileAvatar); ?>" alt="Current profile photo"><?php else: ?><?php echo esc($profileInitial); ?><?php endif; ?>
      </div>
      <div class="profile-editor-upload-field">
        <label for="avatar">Profile photo <span>(optional)</span></label>
        <input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp,image/gif">
        <p class="profile-editor-hint">JPG, PNG, WEBP, or GIF. Maximum 2 MB.</p>
      </div>
    </div>
    <div class="profile-editor-actions">
      <?php if (!empty($profileFormShowBack)): ?><a class="profile-editor-back" href="index.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to chats</a><?php else: ?><span></span><?php endif; ?>
      <button class="profile-editor-save" type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> <?php echo esc($profileEditorSaveLabel ?? 'Save profile'); ?></button>
    </div>
  </form>
</section>
<script>
  (() => {
    const input = document.getElementById('avatar');
    const previews = [document.getElementById('profileEditorAvatar'), document.getElementById('profileEditorUploadAvatar')];
    input?.addEventListener('change', () => {
      const file = input.files?.[0];
      if (!file || !file.type.startsWith('image/')) return;
      const reader = new FileReader();
      reader.onload = () => previews.forEach(preview => {
        if (!preview) return;
        preview.replaceChildren();
        const image = new Image(); image.alt = 'Selected profile photo preview'; image.src = reader.result; preview.appendChild(image);
      });
      reader.readAsDataURL(file);
    });
  })();
</script>
