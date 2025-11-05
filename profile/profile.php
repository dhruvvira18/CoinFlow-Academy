<?php
$pageTitle = 'My Profile';

// START Output Buffer
ob_start();
?>

<?php require_once '../global_navigation.php'; ?>

<?php
// Authentication Check
if (!$user_id) {
    header("Location: ../account/login.html");
    exit();
}

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($mysqli->connect_errno) {
    die("Database connection failed: " . $mysqli->connect_error);
}

// Get a default cosmetic if none is equipped for a type (used for preview)
function getDefaultCosmetic($type) {
    $defaultImage = match($type) {
        'Avatar' => 'https://placehold.co/100x100/343a40/ffffff?text=Default+Avatar',
        'Frame' => 'https://placehold.co/120x120/495057/ffffff?text=Default+Frame',
        'Badge' => 'https://placehold.co/180x60/868e96/ffffff?text=No+Badge+Equipped'
    };
    return [
        'name' => 'None Equipped',
        'image_url' => $defaultImage,
        'cosmetic_id' => 0, // Placeholder ID for "no item"
        'source_table' => ''
    ];
}

// --- Data Fetching ---
$equippedCosmetics = [
    'Avatar' => getDefaultCosmetic('Avatar'),
    'Frame' => getDefaultCosmetic('Frame'),
    'Badge' => getDefaultCosmetic('Badge')
];
$ownedCosmetics = [
    'Avatar' => [],
    'Frame' => [],
    'Badge' => []
];
$username = 'Guest'; // Default fallback


try {
    // 0. Fetch Username from Users table
    $userQuery = "SELECT username FROM Users WHERE user_id = ?";
    $userStmt = $mysqli->prepare($userQuery);
    if ($userStmt) {
        $userStmt->bind_param("s", $user_id);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        if ($userData = $userResult->fetch_assoc()) {
            $username = $userData['username'];
        }
        $userStmt->close();
    }


    // 1. Fetch CURRENTLY EQUIPPED cosmetics (by checking is_equipped = 1 in ownership tables)
    $equippedQuery = "
        (
            SELECT
                wc.type,
                uwc.cosmetic_id,
                'weekly' AS source_table,
                wc.name,
                wc.image_url
            FROM
                user_weekly_cosmetics uwc
            JOIN
                Weekly_Cosmetics wc ON uwc.cosmetic_id = wc.cosmetic_id
            WHERE
                uwc.user_id = ? AND uwc.is_equipped = 1
        )
        UNION
        (
            SELECT
                bc.type,
                ubc.cosmetic_id,
                'bundle' AS source_table,
                bc.name,
                bc.image_url
            FROM
                user_bundle_cosmetics ubc
            JOIN
                Bundle_Cosmetics bc ON ubc.cosmetic_id = bc.cosmetic_id
            WHERE
                ubc.user_id = ? AND ubc.is_equipped = 1
        )
    ";
    $equippedStmt = $mysqli->prepare($equippedQuery);
    if (!$equippedStmt) { throw new Exception("Equipped Query Fail: " . $mysqli->error); }
    // Note: Bind the user_id twice for the UNION query
    $equippedStmt->bind_param("ss", $user_id, $user_id);
    $equippedStmt->execute();
    $result = $equippedStmt->get_result();

    while ($item = $result->fetch_assoc()) {
        $equippedCosmetics[$item['type']] = $item;
    }
    $equippedStmt->close();


    // 2. Fetch ALL OWNED cosmetics (Combined from ownership tables)
    $ownedQuery = "
        (
            SELECT 
                wc.cosmetic_id, 
                wc.name, 
                wc.type, 
                wc.image_url, 
                'weekly' AS source_table,
                uwc.is_equipped
            FROM 
                user_weekly_cosmetics uwc
            JOIN 
                Weekly_Cosmetics wc ON uwc.cosmetic_id = wc.cosmetic_id
            WHERE 
                uwc.user_id = ?
        )
        UNION
        (
            SELECT 
                bc.cosmetic_id, 
                bc.name, 
                bc.type, 
                bc.image_url, 
                'bundle' AS source_table,
                ubc.is_equipped
            FROM 
                user_bundle_cosmetics ubc
            JOIN 
                Bundle_Cosmetics bc ON ubc.cosmetic_id = bc.cosmetic_id
            WHERE 
                ubc.user_id = ?
        )
        ORDER BY name ASC
    ";
    
    $ownedStmt = $mysqli->prepare($ownedQuery);
    if (!$ownedStmt) { throw new Exception("Owned Query Fail: " . $mysqli->error); }
    $ownedStmt->bind_param("ss", $user_id, $user_id);
    $ownedStmt->execute();
    $result = $ownedStmt->get_result();

    // Grouping the combined results by cosmetic type (Avatar, Frame, Badge)
    while ($item = $result->fetch_assoc()) {
        $ownedCosmetics[$item['type']][] = $item;
    }
    $ownedStmt->close();

} catch (Exception $e) {
    error_log("Profile Page Error: " . $e->getMessage());
    // Handle error gracefully on the page if necessary
}

$equippedAvatar = $equippedCosmetics['Avatar'];
$equippedFrame = $equippedCosmetics['Frame'];
$equippedBadge = $equippedCosmetics['Badge'];
?>

<!-- HTML for Profile Page -->
<link rel="stylesheet" href="profile_styles.css">

<div class="container profile-container mt-4">
    <!-- Use the fetched $username here -->
    <h1 class="text-center display-4 mb-5">
        <i class="fas fa-user-circle mr-2"></i> <?php echo htmlspecialchars($username); ?>'s Profile
    </h1>
    
    <!-- === EQUIPPED PREVIEW SECTION === -->
    <div class="equipped-preview-card text-center mb-5 p-4 mx-auto rounded-lg shadow-lg">
        <h2 class="mb-4">Equipped Cosmetics Preview</h2>

        <br><br>
        
        <div class="d-flex justify-content-center align-items-center mb-4">
            <!-- Container for Avatar and Frame -->
            <div class="preview-stage position-relative d-inline-block">
                <!-- 1. Frame -->
                <img id="equipped_frame_image" src="<?php echo htmlspecialchars($equippedFrame['image_url']); ?>" 
                    alt="Equipped Frame" class="equipped-frame position-absolute">
                
                <!-- 2. Avatar -->
                <img id="equipped_avatar_image" src="<?php echo htmlspecialchars($equippedAvatar['image_url']); ?>" 
                    alt="Equipped Avatar" class="equipped-avatar">
            </div>
            
            <!-- 3. Badge (Displayed next to or below the main preview) -->
            <div class="badge-display-area ml-4">
                <p class="text-white mb-2 font-weight-bold">Current Badge:</p>
                <img id="equipped_badge_image" src="<?php echo htmlspecialchars($equippedBadge['image_url']); ?>" 
                    alt="Equipped Badge" class="equipped-badge-seperate">
            </div>
        </div>
        <br>
        <div class="equipped-details mt-4">
            <p><strong>Avatar:</strong> <span id="equipped_avatar_name"><?php echo htmlspecialchars($equippedAvatar['name']); ?></span></p>
            <p><strong>Frame:</strong> <span id="equipped_frame_name"><?php echo htmlspecialchars($equippedFrame['name']); ?></span></p>
            <p><strong>Badge:</strong> <span id="equipped_badge_name"><?php echo htmlspecialchars($equippedBadge['name']); ?></span></p>
        </div>
    </div>

    <!-- === INVENTORY / EQUIPMENT SECTION === -->
    <h2 class="text-center mb-4 inventory-title">Your Inventory</h2>

    <div class="inventory-tabs-container mb-4">
        <button class="btn btn-lg inventory-tab-btn active" data-tab="Avatar">Avatars (<?php echo count($ownedCosmetics['Avatar']); ?>)</button>
        <button class="btn btn-lg inventory-tab-btn" data-tab="Frame">Frames (<?php echo count($ownedCosmetics['Frame']); ?>)</button>
        <button class="btn btn-lg inventory-tab-btn" data-tab="Badge">Badges (<?php echo count($ownedCosmetics['Badge']); ?>)</button>
    </div>

    <div class="inventory-content-wrapper">
        <?php 
        $cosmeticTypes = ['Avatar', 'Frame', 'Badge'];
        foreach ($cosmeticTypes as $type) {
            $currentEquipped = $equippedCosmetics[$type];
        ?>
        <div id="<?php echo $type; ?>_inventory" class="inventory-content row row-cols-2 row-cols-md-4 g-4" style="display: <?php echo $type === 'Avatar' ? 'flex' : 'none'; ?>;">
            <?php 
            // Add the "None Equipped" option first
            $noneEquippedData = getDefaultCosmetic($type);
            // Check if nothing is currently equipped for this type (cosmetic_id 0 is default)
            $isActive = $currentEquipped['cosmetic_id'] === 0; 
            ?>
            <div class="col mb-4">
                <div class="card cosmetic-item-card h-100 text-center <?php echo $isActive ? 'equipped' : ''; ?>"
                     data-cosmetic-id="0" 
                     data-cosmetic-type="<?php echo $type; ?>" 
                     data-source-table="">
                    <div class="card-body d-flex flex-column justify-content-center align-items-center">
                        <i class="fas fa-times-circle fa-3x mb-2" style="color: #dc3545;"></i>
                        <h5 class="card-title"><?php echo $noneEquippedData['name']; ?></h5>
                        <?php if ($isActive) { ?>
                        <span class="badge badge-success equipped-tag">EQUIPPED</span>
                        <?php } else { ?>
                        <button class="btn btn-primary btn-sm equip-btn mt-auto" style="width: 80%;">Equip</button>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <?php 
            // List all owned cosmetics for this type
            foreach ($ownedCosmetics[$type] as $item) { 
                // Check if this specific item is equipped using the is_equipped flag from the fetch
                $isActive = $item['is_equipped'] == 1;
            ?>
            <div class="col mb-4">
                <div class="card cosmetic-item-card h-100 text-center <?php echo $isActive ? 'equipped' : ''; ?>"
                     data-cosmetic-id="<?php echo $item['cosmetic_id']; ?>" 
                     data-cosmetic-type="<?php echo $type; ?>" 
                     data-source-table="<?php echo $item['source_table']; ?>">
                    <img src="<?php echo htmlspecialchars($item['image_url']); ?>" class="card-img-top mx-auto mt-2 equip-image" 
                         alt="<?php echo htmlspecialchars($item['name']); ?>">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title mt-auto text-white"><?php echo htmlspecialchars($item['name']); ?></h5>
                        <?php if ($isActive) { ?>
                        <span class="badge badge-success equipped-tag">EQUIPPED</span>
                        <?php } else { ?>
                        <button class="btn btn-primary btn-sm equip-btn mt-auto" style="width: 80%;">Equip</button>
                        <?php } ?>
                    </div>
                </div>
            </div>
            <?php } ?>
        </div>
        <?php } ?>
    </div>
</div>

<script>
    // --- JavaScript for Tab Switching and Equipping ---

    function switchTab(tabName) {
        // Update tab buttons
        document.querySelectorAll('.inventory-tab-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelector(`.inventory-tab-btn[data-tab='${tabName}']`).classList.add('active');

        // Update content visibility
        document.querySelectorAll('.inventory-content').forEach(content => content.style.display = 'none');
        document.getElementById(tabName + '_inventory').style.display = 'flex'; // Use flex for row-cols
    }

    function handleEquip(cardElement) {
        const cosmeticId = cardElement.dataset.cosmeticId;
        const type = cardElement.dataset.cosmeticType;
        const sourceTable = cardElement.dataset.sourceTable;
        const cosmeticName = cardElement.querySelector('.card-title').textContent;

        // Find the image URL from the card. If it's the "None Equipped" card, use the default placeholder.
        const imageUrl = cardElement.querySelector('.equip-image') ? cardElement.querySelector('.equip-image').src : 
                         (type === 'Avatar' ? 'https://placehold.co/100x100/343a40/ffffff?text=Default+Avatar' : 
                          (type === 'Frame' ? 'https://placehold.co/120x120/495057/ffffff?text=Default+Frame' : 
                          'https://placehold.co/180x60/868e96/ffffff?text=No+Badge+Equipped'));


        let actionType = (cosmeticId === '0') ? 'unequip' : 'equip';

        // 1. AJAX call to the backend handler
        $.ajax({
            url: 'equip_cosmetic.php',
            type: 'POST',
            data: {
                action: actionType,
                cosmetic_id: cosmeticId,
                type: type,
                source: sourceTable
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    console.log(response.message);
                    
                    // 2. Update the Equipped Preview
                    updatePreview(type, cosmeticName, imageUrl);

                    // 3. Update the Inventory Tags (Move the "EQUIPPED" tag)
                    updateInventoryTags(type, cosmeticId, sourceTable);
                    
                } else {
                    alert('Error equipping item: ' + response.message);
                }
            },
            error: function(jqXHR) {
                const errorData = jqXHR.responseJSON || { message: 'Unknown error during equip.' };
                alert('Server Error: ' + errorData.message);
            }
        });
    }

    function updatePreview(type, name, imageUrl) {
        const typeLower = type.toLowerCase();
        
        // Update image (frame, avatar, badge)
        const imageElement = document.getElementById(`equipped_${typeLower}_image`);
        if (imageElement) {
            imageElement.src = imageUrl;
        }

        // Update name
        const nameElement = document.getElementById(`equipped_${typeLower}_name`);
        if (nameElement) {
            nameElement.textContent = name;
        }
    }

    function updateInventoryTags(type, equippedId, equippedSource) {
        // Find all cards of the current type in the inventory
        document.querySelectorAll(`#${type}_inventory .cosmetic-item-card`).forEach(card => {
            const currentId = card.dataset.cosmeticId;
            const currentSource = card.dataset.sourceTable;
            const equipButton = card.querySelector('.equip-btn');
            let isEquipped = false;

            if (equippedId === '0') {
                // If equipping "None Equipped"
                isEquipped = (currentId === '0');
            } else {
                // If equipping a specific item
                isEquipped = (currentId === equippedId && currentSource === equippedSource);
            }
            
            // 1. Manage the 'equipped' class
            if (isEquipped) {
                card.classList.add('equipped');
                if (equipButton) equipButton.style.display = 'none';
                if (!card.querySelector('.equipped-tag')) {
                    const tag = document.createElement('span');
                    tag.className = 'badge badge-success equipped-tag';
                    tag.textContent = 'EQUIPPED';
                    card.querySelector('.card-body').appendChild(tag);
                }
            } else {
                card.classList.remove('equipped');
                const tag = card.querySelector('.equipped-tag');
                if (tag) tag.remove();
                if (equipButton) equipButton.style.display = 'block';
            }

            // 2. Re-render the "None Equipped" card content if it's the current one
            if (currentId === '0') {
                const defaultIcon = `<i class="fas fa-times-circle fa-3x mb-2" style="color: #dc3545;"></i>`;
                const contentHtml = `
                    ${defaultIcon}
                    <h5 class="card-title">None Equipped</h5>
                    ${isEquipped ? '<span class="badge badge-success equipped-tag">EQUIPPED</span>' : '<button class="btn btn-primary btn-sm equip-btn mt-auto" style="width: 80%;">Equip</button>'}
                 `;
                 // Find the card-body and update its content
                 const cardBody = card.querySelector('.card-body');
                 if (cardBody) {
                     cardBody.innerHTML = contentHtml;
                 }
            }

        });
    }


    $(document).ready(function() {
        // Initial setup for tabs
        $('.inventory-tab-btn').click(function() {
            const tabName = $(this).data('tab');
            switchTab(tabName);
        });

        // Event listener for the Equip button
        $(document).on('click', '.equip-btn', function(e) {
            e.stopPropagation(); // Prevent the card click handler from firing too
            // Traverse up to get the parent card's data
            const card = $(this).closest('.cosmetic-item-card')[0];
            handleEquip(card);
        });

        // Event listener for clicking the card itself (makes it easier to equip)
        $(document).on('click', '.cosmetic-item-card:not(.equipped)', function() {
            handleEquip(this);
        });
        
        // Initial tab load
        switchTab('Avatar');
    });
</script>

<?php
$mysqli->close();
$pageContent = ob_get_clean();

// Load the complete layout
require '../layout.php';
?>