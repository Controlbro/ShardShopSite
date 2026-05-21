<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/install.php';
require_once __DIR__ . '/../app/functions.php';

$user = require_shop_editor();
$pdo = db();
$notice = null;
$error = null;

function posted_commands(): array
{
    $posted = $_POST['commands'] ?? [];
    if (!is_array($posted)) {
        return [];
    }

    $commands = [];
    foreach ($posted as $command) {
        $command = trim((string) $command);
        if ($command !== '') {
            $commands[] = $command;
        }
    }

    return $commands;
}

function posted_item_payload(): array
{
    $name = trim((string) ($_POST['name'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $imageUrl = trim((string) ($_POST['image_url'] ?? ''));
    $category = trim((string) ($_POST['category'] ?? 'General'));
    $price = max(0, (int) ($_POST['price'] ?? 0));
    $sortOrder = (int) ($_POST['sort_order'] ?? 0);
    $commands = posted_commands();

    if ($name === '') {
        throw new RuntimeException('Item name is required.');
    }
    if ($category === '') {
        $category = 'General';
    }
    if ($commands === []) {
        throw new RuntimeException('Add at least one command for this item.');
    }
    if ($imageUrl !== '' && filter_var($imageUrl, FILTER_VALIDATE_URL) === false && !str_starts_with($imageUrl, '/')) {
        throw new RuntimeException('Image URL must be a full URL or a site-relative path beginning with /.');
    }

    return [
        'name' => $name,
        'description' => $description === '' ? null : $description,
        'image_url' => $imageUrl === '' ? null : $imageUrl,
        'price' => $price,
        'category' => $category,
        'commands' => $commands,
        'enabled' => isset($_POST['enabled']) ? 1 : 0,
        'sort_order' => $sortOrder,
        'one_time_purchase' => isset($_POST['one_time_purchase']) ? 1 : 0,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $action = (string) ($_POST['action'] ?? '');
        $itemId = (int) ($_POST['item_id'] ?? 0);

        if ($action === 'create') {
            $payload = posted_item_payload();
            $stmt = $pdo->prepare('INSERT INTO webshop_items (name, description, image_url, price, category, commands, enabled, sort_order, one_time_purchase) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$payload['name'], $payload['description'], $payload['image_url'], $payload['price'], $payload['category'], json_encode($payload['commands'], JSON_UNESCAPED_SLASHES), $payload['enabled'], $payload['sort_order'], $payload['one_time_purchase']]);
            audit_log($user['uuid'], $user['username'], 'item_created', 'Created shop item: ' . $payload['name']);
            $notice = 'Created “' . $payload['name'] . '”.';
        } elseif ($action === 'save') {
            if ($itemId <= 0) {
                throw new RuntimeException('Missing item id.');
            }
            $payload = posted_item_payload();
            $stmt = $pdo->prepare('UPDATE webshop_items SET name = ?, description = ?, image_url = ?, price = ?, category = ?, commands = ?, enabled = ?, sort_order = ?, one_time_purchase = ? WHERE id = ?');
            $stmt->execute([$payload['name'], $payload['description'], $payload['image_url'], $payload['price'], $payload['category'], json_encode($payload['commands'], JSON_UNESCAPED_SLASHES), $payload['enabled'], $payload['sort_order'], $payload['one_time_purchase'], $itemId]);
            audit_log($user['uuid'], $user['username'], 'item_updated', 'Updated shop item #' . $itemId . ': ' . $payload['name']);
            $notice = 'Saved “' . $payload['name'] . '”.';
        } elseif ($action === 'clone') {
            if ($itemId <= 0) {
                throw new RuntimeException('Missing item id.');
            }
            $stmt = $pdo->prepare('SELECT * FROM webshop_items WHERE id = ? LIMIT 1');
            $stmt->execute([$itemId]);
            $item = $stmt->fetch();
            if (!$item) {
                throw new RuntimeException('Item not found.');
            }
            $cloneName = substr((string) $item['name'] . ' Copy', 0, 100);
            $insert = $pdo->prepare('INSERT INTO webshop_items (name, description, image_url, price, category, commands, enabled, sort_order, one_time_purchase) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $insert->execute([$cloneName, $item['description'], $item['image_url'], (int) $item['price'], $item['category'], $item['commands'], 0, (int) $item['sort_order'] + 1, (int) ($item['one_time_purchase'] ?? 0)]);
            audit_log($user['uuid'], $user['username'], 'item_cloned', 'Cloned shop item #' . $itemId . ' as #' . $pdo->lastInsertId());
            $notice = 'Cloned “' . $item['name'] . '” as a disabled copy.';
        } elseif ($action === 'delete') {
            if ($itemId <= 0) {
                throw new RuntimeException('Missing item id.');
            }
            $stmt = $pdo->prepare('SELECT name FROM webshop_items WHERE id = ? LIMIT 1');
            $stmt->execute([$itemId]);
            $name = (string) ($stmt->fetchColumn() ?: 'item #' . $itemId);
            $delete = $pdo->prepare('DELETE FROM webshop_items WHERE id = ?');
            $delete->execute([$itemId]);
            audit_log($user['uuid'], $user['username'], 'item_deleted', 'Deleted shop item #' . $itemId . ': ' . $name);
            $notice = 'Deleted “' . $name . '”.';
        } else {
            throw new RuntimeException('Unknown item editor action.');
        }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$items = $pdo->query('SELECT * FROM webshop_items ORDER BY enabled DESC, sort_order, category, name')->fetchAll();

render_header('Edit Shop Items');
?>
<section class="section-head admin-hero">
    <div>
        <p class="eyebrow">CBYT tools</p>
        <h1>Edit Shop Items</h1>
        <p>Manage rewards, image previews, command queues, cloning, and deletes from one smart editor.</p>
    </div>
    <a class="btn btn-secondary" href="/shop.php">View Shop</a>
</section>

<?php if ($notice !== null): ?><div class="alert alert-success"><?= e($notice) ?></div><?php endif; ?>
<?php if ($error !== null): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<section class="admin-layout">
    <aside class="admin-create glass-card">
        <p class="eyebrow">New reward</p>
        <h2>Add Item</h2>
        <form class="admin-item-form" method="post" data-admin-item-form>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="admin-preview" data-image-preview><span>✦</span></div>
            <label>Name <input type="text" name="name" maxlength="100" placeholder="Diamond Pack" required></label>
            <div class="admin-field-grid">
                <label>Price <input type="number" name="price" min="0" value="0" required></label>
                <label>Sort <input type="number" name="sort_order" value="0"></label>
            </div>
            <label>Category <input type="text" name="category" value="General" maxlength="64" required></label>
            <label>Image URL <input type="text" name="image_url" placeholder="https://... or /assets/item.png" data-image-input></label>
            <label>Description <textarea name="description" rows="3" placeholder="Describe the reward"></textarea></label>
            <label class="toggle-row"><input type="checkbox" name="enabled" checked> Enabled in shop</label>
            <label class="toggle-row"><input type="checkbox" name="one_time_purchase"> One-time purchase (limit 1 per player)</label>
            <div class="commands-editor" data-commands-editor>
                <div class="commands-title"><strong>Commands</strong><button class="btn btn-secondary btn-small" type="button" data-add-command>+ Add command</button></div>
                <label class="command-box">Command 1<textarea name="commands[]" rows="2" placeholder="give {player} diamond 16" required></textarea><button type="button" class="command-remove" data-remove-command aria-label="Remove command">×</button></label>
            </div>
            <button class="btn full" type="submit">Create Item</button>
        </form>
    </aside>

    <div class="admin-items-list">
        <?php foreach ($items as $item): ?>
            <?php
                $commands = json_decode((string) $item['commands'], true);
                if (!is_array($commands) || $commands === []) {
                    $commands = [''];
                }
            ?>
            <article class="admin-item-card <?= (int) $item['enabled'] === 1 ? '' : 'is-disabled' ?>">
                <form class="admin-item-form" method="post" data-admin-item-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                    <div class="admin-card-top">
                        <div class="admin-preview" data-image-preview>
                            <?php if (!empty($item['image_url'])): ?>
                                <img src="<?= e($item['image_url']) ?>" alt="<?= e($item['name']) ?> preview">
                            <?php else: ?>
                                <span>✦</span>
                            <?php endif; ?>
                        </div>
                        <div class="admin-main-fields">
                            <div class="admin-item-meta"><span>#<?= (int) $item['id'] ?></span><span><?= (int) $item['enabled'] === 1 ? 'Enabled' : 'Disabled' ?></span></div>
                            <label>Name <input type="text" name="name" maxlength="100" value="<?= e($item['name']) ?>" required></label>
                            <div class="admin-field-grid">
                                <label>Price <input type="number" name="price" min="0" value="<?= (int) $item['price'] ?>" required></label>
                                <label>Sort <input type="number" name="sort_order" value="<?= (int) $item['sort_order'] ?>"></label>
                            </div>
                        </div>
                    </div>
                    <div class="admin-field-grid wide">
                        <label>Category <input type="text" name="category" maxlength="64" value="<?= e($item['category']) ?>" required></label>
                        <label>Image URL <input type="text" name="image_url" value="<?= e($item['image_url']) ?>" data-image-input></label>
                    </div>
                    <label>Description <textarea name="description" rows="3"><?= e($item['description']) ?></textarea></label>
                    <label class="toggle-row"><input type="checkbox" name="enabled" <?= (int) $item['enabled'] === 1 ? 'checked' : '' ?>> Enabled in shop</label>
                    <label class="toggle-row"><input type="checkbox" name="one_time_purchase" <?= (int) ($item['one_time_purchase'] ?? 0) === 1 ? 'checked' : '' ?>> One-time purchase (limit 1 per player)</label>
                    <div class="commands-editor" data-commands-editor>
                        <div class="commands-title"><strong>Commands</strong><button class="btn btn-secondary btn-small" type="button" data-add-command>+ Add command</button></div>
                        <?php foreach ($commands as $index => $command): ?>
                            <label class="command-box">Command <?= $index + 1 ?><textarea name="commands[]" rows="2" required><?= e((string) $command) ?></textarea><button type="button" class="command-remove" data-remove-command aria-label="Remove command">×</button></label>
                        <?php endforeach; ?>
                    </div>
                    <div class="admin-actions">
                        <button class="btn" type="submit" name="action" value="save">Save</button>
                        <button class="btn btn-secondary" type="submit" name="action" value="clone" formnovalidate>Clone</button>
                        <button class="btn btn-danger" type="submit" name="action" value="delete" data-confirm-delete formnovalidate>Delete</button>
                    </div>
                </form>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php render_footer(); ?>
