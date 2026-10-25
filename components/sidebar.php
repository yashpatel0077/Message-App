    <aside class="mini-sidebar">
      <div class="mini-top">
        <div class="mini-item mini-item-primary" title="Unread">
          <i class="fas fa-inbox"></i>
          <?php if ($unreadCount > 0): ?><span class="mini-badge">
              <?php echo (int) $unreadCount; ?>
            </span>
          <?php endif; ?>
        </div>
        <div class="mini-item" title="Create group" onclick="openCreateGroup()"><i class="fas fa-users"></i></div>
      </div>
      <div class="mini-bottom">
        <div class="mini-item mini-small" title="Settings" onclick="location.href='settings.php'"><i
            class="fas fa-cog"></i></div>
        <div class="mini-item mini-small" title="Profile" onclick="location.href='set_profile.php?edit=1'"><i
            class="fas fa-user"></i></div>
      </div>
    </aside>

    <section class="chat-sidebar" id="chatSidebar">
      <div class="chat-sidebar-header">
        <div class="chat-sidebar-title"><span class="brand-mark"><i class="fa-solid fa-comment-dots" aria-hidden="true"></i></span>WeChats</div>
        <div class="chat-sidebar-actions">
          <button type="button" class="icon-btn" title="Add user" onclick="openFindUserPopup()"><i
              class="fas fa-user-plus"></i></button>
          <button type="button" class="icon-btn" title="More" onclick="toggleChatsMenu(event)"><i
              class="fas fa-ellipsis-v"></i></button>

          <div class="dropdown" id="chatsMenu" style="display:none;">
            <button type="button" class="dropdown-item" onclick="openFindUserPopup(); closeChatsMenu();"><i
                class="fas fa-user-plus"></i> Add user</button>
            <button type="button" class="dropdown-item" onclick="openCreateGroup(); closeChatsMenu();"><i
                class="fas fa-users"></i> Create group</button>
            <button type="button" class="dropdown-item" onclick="openHiddenChats(); closeChatsMenu();"><i
                class="fas fa-eye"></i> Unhide</button>
            <button type="button" class="dropdown-item" onclick="location.href='set_profile.php?edit=1'"><i
                class="fas fa-user"></i> Profile</button>
            <button type="button" class="dropdown-item" onclick="location.href='settings.php'"><i
                class="fas fa-cog"></i> Settings</button>
            <button type="button" class="dropdown-item" onclick="location.href='logout.php'"><i
                class="fas fa-right-from-bracket"></i> Logout</button>
          </div>
        </div>
      </div>

      <div class="chat-sidebar-search">
        <i class="fas fa-search"></i>
        <input type="text" id="searchInput" placeholder="Search" onkeyup="filterChats()">
      </div>

      <div class="chat-sidebar-filters">
        <button type="button" class="filter-pill <?php echo ($filter === 'all' ? 'filter-pill-active' : ''); ?>"
          onclick="setFilter('all')">All</button>
        <button type="button" class="filter-pill <?php echo ($filter === 'unread' ? 'filter-pill-active' : ''); ?>"
          onclick="setFilter('unread')">Unread</button>
        <button type="button" class="filter-pill <?php echo ($filter === 'groups' ? 'filter-pill-active' : ''); ?>"
          onclick="setFilter('groups')">Groups</button>
      </div>

      <div class="chat-sidebar-list" id="contactsList">
        <?php if ($filter === 'all'): ?>

          <?php if (count($all_items) > 0): ?>
            <?php foreach ($all_items as $it): ?>
              <?php
              $type = $it['type'];
              $id = (int) $it['id'];
              $name = (string) $it['name'];
              $av = (string) $it['avatar'];
              $time = !empty($it['last_time']) ? date('H:i', strtotime($it['last_time'])) : '';
              $un = (int) ($it['unread_count'] ?? 0);

              $active = '';
              if ($type === 'user' && $id === $selected_user_id)
                $active = 'chat-row-active';
              if ($type === 'group' && $id === $selected_group_id)
                $active = 'chat-row-active';
              ?>
              <div class="chat-row contact-row <?php echo $active; ?>" data-name="<?php echo esc($name); ?>"
                onclick="<?php echo ($type === 'user') ? "openChat($id)" : "openGroup($id)"; ?>">
                <div class="chat-avatar" style="background-image:url('<?php echo esc($av); ?>')"></div>
                <div class="chat-main-info">
                  <div class="chat-name contact-name">
                    <?php echo esc($name); ?>
                  </div>
                  <div class="chat-preview">
                    <?php echo $type === 'group' ? 'Group' : 'Chat'; ?>
                  </div>
                </div>
                <div class="chat-meta">
                  <span class="chat-time">
                    <?php echo esc($time); ?>
                  </span>
                  <?php if ($type === 'user' && $un > 0): ?><span class="unread-badge">
                      <?php echo $un; ?>
                    </span>
                  <?php endif; ?>
                </div>

                <div class="chat-actions" onclick="event.stopPropagation();">
                  <div class="chat-more-wrap">
                    <button class="chat-action-btn" type="button"
                      onclick="toggleItemMore(event,'<?php echo $type; ?>',<?php echo $id; ?>)"><i
                        class="fas fa-ellipsis-v"></i></button>
                    <div class="chat-more-menu" id="itemMore_<?php echo $type; ?>_<?php echo $id; ?>">
                      <?php if ($type === 'user'): ?>
                        <button type="button" class="chat-more-item"
                          onclick="openUserProfile(<?php echo $id; ?>); closeAllItemMore();"><i class="fas fa-user"></i> See
                          profile</button>
                        <button type="button" class="chat-more-item"
                          onclick="pinChat(<?php echo $id; ?>); closeAllItemMore();"><i class="fas fa-thumbtack"></i> Pin /
                          Unpin</button>
                        <button type="button" class="chat-more-item"
                          onclick="hideChat(<?php echo $id; ?>); closeAllItemMore();"><i class="fas fa-eye-slash"></i> Hide
                          chat</button>
                        <button type="button" class="chat-more-item chat-more-danger"
                          onclick="deleteChat(<?php echo $id; ?>); closeAllItemMore();"><i class="fas fa-trash"></i> Delete
                          chat</button>
                      <?php else: ?>
                        <button type="button" class="chat-more-item"
                          onclick="openGroupMembers(<?php echo $id; ?>); closeAllItemMore();"><i class="fas fa-users"></i> View
                          members</button>
                        <button type="button" class="chat-more-item"
                          onclick="pinGroup(<?php echo $id; ?>); closeAllItemMore();"><i class="fas fa-thumbtack"></i> Pin /
                          Unpin</button>
                        <button type="button" class="chat-more-item"
                          onclick="hideGroup(<?php echo $id; ?>); closeAllItemMore();"><i class="fas fa-eye-slash"></i> Hide
                          group</button>
                        <button type="button" class="chat-more-item chat-more-danger"
                          onclick="deleteGroupChat(<?php echo $id; ?>); closeAllItemMore();"><i class="fas fa-trash"></i> Delete
                          group chat</button>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>

              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="chat-row chat-row-demo">
              <div class="chat-main-info">
                <div class="chat-name">No chats</div>
                <div class="chat-preview">Start a conversation or create a group.</div>
              </div>
            </div>
          <?php endif; ?>

        <?php elseif ($filter === 'groups'): ?>

          <?php if ($groups_list && $groups_list->num_rows > 0): ?>
            <?php while ($g = $groups_list->fetch_assoc()): ?>
              <?php $gid = (int) $g['id'];
              $gname = (string) $g['name'];
              $gav = avatar_fallback($gname); ?>
              <div class="chat-row contact-row <?php echo ($gid === $selected_group_id) ? 'chat-row-active' : ''; ?>"
                data-name="<?php echo esc($gname); ?>" onclick="openGroup(<?php echo $gid; ?>)">
                <div class="chat-avatar" style="background-image:url('<?php echo esc($gav); ?>')"></div>
                <div class="chat-main-info">
                  <div class="chat-name contact-name">
                    <?php echo esc($gname); ?>
                  </div>
                  <div class="chat-preview">Group</div>
                </div>
                <div class="chat-meta"><span class="chat-time"></span></div>

                <div class="chat-actions" onclick="event.stopPropagation();">
                  <div class="chat-more-wrap">
                    <button class="chat-action-btn" type="button"
                      onclick="toggleItemMore(event,'group',<?php echo $gid; ?>)"><i class="fas fa-ellipsis-v"></i></button>
                    <div class="chat-more-menu" id="itemMore_group_<?php echo $gid; ?>">
                      <button type="button" class="chat-more-item"
                        onclick="openGroupMembers(<?php echo $gid; ?>); closeAllItemMore();"><i class="fas fa-users"></i> View
                        members</button>
                      <button type="button" class="chat-more-item"
                        onclick="pinGroup(<?php echo $gid; ?>); closeAllItemMore();"><i class="fas fa-thumbtack"></i> Pin /
                        Unpin</button>
                      <button type="button" class="chat-more-item"
                        onclick="hideGroup(<?php echo $gid; ?>); closeAllItemMore();"><i class="fas fa-eye-slash"></i> Hide
                        group</button>
                      <button type="button" class="chat-more-item chat-more-danger"
                        onclick="deleteGroupChat(<?php echo $gid; ?>); closeAllItemMore();"><i class="fas fa-trash"></i>
                        Delete group chat</button>
                    </div>
                  </div>
                </div>

              </div>
            <?php endwhile; ?>
          <?php else: ?>
            <div class="chat-row chat-row-demo">
              <div class="chat-main-info">
                <div class="chat-name">No groups</div>
                <div class="chat-preview">Create your first group.</div>
              </div>
            </div>
          <?php endif; ?>

        <?php else: /* unread */?>

          <?php if ($contacts && $contacts->num_rows > 0): ?>
            <?php while ($c = $contacts->fetch_assoc()): ?>
              <?php
              $cid = (int) $c['id'];
              $name = user_display_name($c);
              $av = !empty($c['avatar']) ? $c['avatar'] : avatar_fallback($name);
              $un = (int) ($c['unread_count'] ?? 0);
              $lastMsg = trim((string) ($c['last_message'] ?? ''));
              $lastMsg = mb_substr($lastMsg, 0, 60) . (mb_strlen($lastMsg) > 60 ? '…' : '');
              $prefix = ((int) ($c['last_sender'] ?? 0) === $user_id) ? 'You: ' : '';
              $time = $c['last_time'] ? date('H:i', strtotime($c['last_time'])) : '';
              ?>
              <div class="chat-row contact-row <?php echo ($cid === $selected_user_id) ? 'chat-row-active' : ''; ?>"
                data-name="<?php echo esc($name); ?>" onclick="openChat(<?php echo $cid; ?>)">
                <div class="chat-avatar" style="background-image:url('<?php echo esc($av); ?>')"></div>
                <div class="chat-main-info">
                  <div class="chat-name contact-name">
                    <?php echo esc($name); ?>
                  </div>
                  <div class="chat-preview">
                    <?php echo esc($prefix . $lastMsg); ?>
                  </div>
                </div>
                <div class="chat-meta">
                  <span class="chat-time">
                    <?php echo esc($time); ?>
                  </span>
                  <?php if ($un > 0): ?><span class="unread-badge">
                      <?php echo $un; ?>
                    </span>
                  <?php endif; ?>
                </div>

                <div class="chat-actions" onclick="event.stopPropagation();">
                  <div class="chat-more-wrap">
                    <button class="chat-action-btn" type="button"
                      onclick="toggleItemMore(event,'user',<?php echo $cid; ?>)"><i class="fas fa-ellipsis-v"></i></button>
                    <div class="chat-more-menu" id="itemMore_user_<?php echo $cid; ?>">
                      <button type="button" class="chat-more-item"
                        onclick="openUserProfile(<?php echo $cid; ?>); closeAllItemMore();"><i class="fas fa-user"></i> See
                        profile</button>
                      <button type="button" class="chat-more-item"
                        onclick="pinChat(<?php echo $cid; ?>); closeAllItemMore();"><i class="fas fa-thumbtack"></i> Pin /
                        Unpin</button>
                      <button type="button" class="chat-more-item"
                        onclick="hideChat(<?php echo $cid; ?>); closeAllItemMore();"><i class="fas fa-eye-slash"></i> Hide
                        chat</button>
                      <button type="button" class="chat-more-item chat-more-danger"
                        onclick="deleteChat(<?php echo $cid; ?>); closeAllItemMore();"><i class="fas fa-trash"></i> Delete
                        chat</button>
                    </div>
                  </div>
                </div>

              </div>
            <?php endwhile; ?>
          <?php else: ?>
            <div class="chat-row chat-row-demo">
              <div class="chat-main-info">
                <div class="chat-name">No chats</div>
                <div class="chat-preview">No unread chats.</div>
              </div>
            </div>
          <?php endif; ?>

        <?php endif; ?>
      </div>
    </section>

