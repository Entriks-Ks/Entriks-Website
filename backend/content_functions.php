<?php
require_once __DIR__ . '/database.php';

$pageContentBlocks = [];
$pageContentLoaded = false;

/**
 * Loads all content blocks for a specific page ID into a local cache
 */
function loadPageBlocks($pageId, $isEditorMode = false)
{
    global $db, $pageContentBlocks, $pageContentLoaded;

    if ($pageContentLoaded)
        return;

    $pageContentBlocks = [];

    if (!isset($db))
        return;

    try {
        $cursor = $db->content_blocks->find(['page_id' => $pageId]);
        foreach ($cursor as $doc) {
            $key = $doc['block_key'];

            $hasDraft = isset($doc['draft_content']) || isset($doc['draft_style']) || isset($doc['draft_is_deleted']);

            if ($isEditorMode && $hasDraft) {
                $content = $doc['draft_content'] ?? ($doc['content'] ?? '');
                $type = $doc['draft_type'] ?? ($doc['type'] ?? 'text');
                $style = $doc['draft_style'] ?? ($doc['style'] ?? '');
                $isDeleted = $doc['draft_is_deleted'] ?? ($doc['is_deleted'] ?? false);
            } else {
                $content = $doc['content'] ?? '';
                $type = $doc['type'] ?? 'text';
                $style = $doc['style'] ?? '';
                $isDeleted = $doc['is_deleted'] ?? false;
            }

            $pageContentBlocks[$key] = [
                'content' => $content,
                'type' => $type,
                'style' => $style,
                'is_deleted' => $isDeleted
            ];
        }
        $pageContentLoaded = true;
    } catch (Exception $e) {
        $pageContentBlocks = [];
    }
}

/**
 * Checks if a block is marked as deleted
 */
function isDeleted($key)
{
    global $pageContentBlocks;
    if (!isset($pageContentBlocks[$key]))
        return false;

    $val = $pageContentBlocks[$key]['is_deleted'];
    return ($val === true || $val === 1 || $val === 'true');
}

/**
 * Renders a content block.
 * @param string $key
 * @param string $default
 * @return string
 */
function renderBlock($key, $default = '')
{
    global $pageContentBlocks;

    if (isDeleted($key))
        return '';

    if (isset($pageContentBlocks[$key]) && (string) $pageContentBlocks[$key]['content'] !== '') {
        return $pageContentBlocks[$key]['content'];
    }

    return $default;
}

/**
 * Returns the content raw, useful for attributes like href or src
 */
function getBlockContent($key, $default = '')
{
    return renderBlock($key, $default);
}

/**
 * Renders the saved style attribute for a block
 */
function renderStyle($key, $default = '')
{
    global $pageContentBlocks;

    if (isset($pageContentBlocks[$key]) && !empty($pageContentBlocks[$key]['style'])) {
        return $pageContentBlocks[$key]['style'];
    }

    return $default;
}

$pageSectionOrder = [];
$pageSectionOrderLoaded = false;

/**
 * Loads the saved section order from the page_structure collection.
 * Call this once after loadPageBlocks().
 */
function loadSectionOrder($pageId)
{
    global $db, $pageSectionOrder, $pageSectionOrderLoaded;
    if ($pageSectionOrderLoaded) return;
    $pageSectionOrderLoaded = true;
    $pageSectionOrder = [];
    if (!isset($db)) return;
    try {
        $doc = $db->page_structure->findOne(['page_id' => $pageId]);
        if ($doc && !empty($doc['structure'])) {
            foreach ($doc['structure'] as $item) {
                // BSONDocument implements ArrayAccess but is_array() returns false for it
                if ($item instanceof MongoDB\Model\BSONDocument || $item instanceof MongoDB\Model\BSONArray) {
                    $item = (array) $item;
                }
                if (is_array($item)) {
                    $id = (string) ($item['id'] ?? '');
                } else {
                    $id = (string) $item;
                }
                if ($id) $pageSectionOrder[] = $id;
            }
        }
    } catch (Exception $e) {}
}
?>