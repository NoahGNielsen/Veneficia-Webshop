SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS VeneficiaCategories (
    categoryId BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    categoryName VARCHAR(150) NOT NULL,
    categoryDescription TEXT NULL,
    parentCategoryId BIGINT UNSIGNED NULL,
    PRIMARY KEY (categoryId),
    CONSTRAINT fk_VeneficiaCategories_parent
        FOREIGN KEY (parentCategoryId) REFERENCES VeneficiaCategories (categoryId)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS VeneficiaCustomers (
    VeneficiaCustomerId BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(254) NOT NULL,
    firstName VARCHAR(100) NOT NULL,
    lastName VARCHAR(100) NOT NULL,
    phoneNumber VARCHAR(32) NULL,
    passwordHash VARCHAR(255) NOT NULL,
    newsLetterConsent BOOLEAN NOT NULL DEFAULT FALSE,
    newsLetterConsentAt DATETIME NULL,
    accountCreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    lastLoginAt DATETIME NULL,
    addressLine VARCHAR(255) NULL,
    addressPostalCode VARCHAR(20) NULL,
    addressCity VARCHAR(100) NULL,
    PRIMARY KEY (VeneficiaCustomerId),
    UNIQUE KEY uq_VeneficiaCustomers_email (email),
    KEY ix_VeneficiaCustomers_newsLetterConsent (newsLetterConsent)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS VeneficiaEmployees (
    employeeID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employeeUsername VARCHAR(100) NOT NULL,
    passwordHash VARCHAR(255) NOT NULL,
    PRIMARY KEY (employeeID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS VeneficiaDiscountCodes (
    discountCodeID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codeName VARCHAR(150) NOT NULL,
    code VARCHAR(100) NOT NULL,
    validAt DATETIME NOT NULL,
    validUntil DATETIME NULL,
    discountType VARCHAR(30) NOT NULL,
    discountAmount DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (discountCodeID),
    UNIQUE KEY uq_VeneficiaDiscountCodes_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS VeneficiaSuppliers (
    supplierId BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    companyName VARCHAR(200) NOT NULL,
    contactName VARCHAR(150) NULL,
    email VARCHAR(254) NULL,
    phoneNumber VARCHAR(32) NULL,
    cvrNumber VARCHAR(32) NULL,
    addressLine VARCHAR(255) NULL,
    addressPostalCode VARCHAR(20) NULL,
    addressCity VARCHAR(100) NULL,
    PRIMARY KEY (supplierId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS VeneficiaAttachments (
    attachmentId BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    fileName VARCHAR(255) NOT NULL,
    fileUrl VARCHAR(2048) NOT NULL,
    mimeType VARCHAR(127) NOT NULL,
    fileSize BIGINT UNSIGNED NOT NULL,
    altText VARCHAR(500) NULL,
    uploadedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (attachmentId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS VeneficiaItems (
    itemId BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    itemName VARCHAR(200) NOT NULL,
    itemDescription TEXT NULL,
    categoryId BIGINT UNSIGNED NOT NULL,
    itemDefaultVariant BIGINT UNSIGNED NULL,
    PRIMARY KEY (itemId),
    CONSTRAINT fk_VeneficiaItems_category
        FOREIGN KEY (categoryId) REFERENCES VeneficiaCategories (categoryId)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS VeneficiaItemVariants (
    itemVariantId BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    itemId BIGINT UNSIGNED NOT NULL,
    varriantType VARCHAR(100) NOT NULL,
    varriantName VARCHAR(150) NOT NULL,
    varriantPrice DECIMAL(10,2) NOT NULL,
    inventoryCount INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (itemVariantId),
    CONSTRAINT fk_VeneficiaItemVariants_item
        FOREIGN KEY (itemId) REFERENCES VeneficiaItems (itemId)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE VeneficiaItems
    ADD CONSTRAINT fk_VeneficiaItems_defaultVariant
        FOREIGN KEY (itemDefaultVariant) REFERENCES VeneficiaItemVariants (itemVariantId)
        ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS VeneficiaOrdre (
    ordreID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customerId BIGINT UNSIGNED NOT NULL,
    ordreStatus VARCHAR(32) NOT NULL,
    ordredAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    addressLine VARCHAR(255) NOT NULL,
    addressPostalCode VARCHAR(20) NOT NULL,
    addressCity VARCHAR(100) NOT NULL,
    discountCodeId BIGINT UNSIGNED NULL,
    shippingPrice DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    shippingWeight DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    PRIMARY KEY (ordreID),
    CONSTRAINT fk_VeneficiaOrdre_customer
        FOREIGN KEY (customerId) REFERENCES VeneficiaCustomers (VeneficiaCustomerId)
        ON DELETE RESTRICT,
    CONSTRAINT fk_VeneficiaOrdre_discountCode
        FOREIGN KEY (discountCodeId) REFERENCES VeneficiaDiscountCodes (discountCodeID)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS VeneficiaWarehouseLog (
    logId BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    itemVariantId BIGINT UNSIGNED NOT NULL,
    employeeId BIGINT UNSIGNED NULL,
    supplierId BIGINT UNSIGNED NULL,
    ordreId BIGINT UNSIGNED NULL,
    changeType VARCHAR(50) NOT NULL,
    quantityChange INT NOT NULL,
    note TEXT NULL,
    loggedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (logId),
    CONSTRAINT fk_VeneficiaWarehouseLog_itemVariant
        FOREIGN KEY (itemVariantId) REFERENCES VeneficiaItemVariants (itemVariantId)
        ON DELETE RESTRICT,
    CONSTRAINT fk_VeneficiaWarehouseLog_employee
        FOREIGN KEY (employeeId) REFERENCES VeneficiaEmployees (employeeID)
        ON DELETE SET NULL,
    CONSTRAINT fk_VeneficiaWarehouseLog_supplier
        FOREIGN KEY (supplierId) REFERENCES VeneficiaSuppliers (supplierId)
        ON DELETE SET NULL,
    CONSTRAINT fk_VeneficiaWarehouseLog_ordre
        FOREIGN KEY (ordreId) REFERENCES VeneficiaOrdre (ordreID)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS VeneficiaWishlistItems (
    customerId BIGINT UNSIGNED NOT NULL,
    itemVariantId BIGINT UNSIGNED NOT NULL,
    itemAddedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (customerId, itemVariantId),
    CONSTRAINT fk_VeneficiaWishlistItems_customer
        FOREIGN KEY (customerId) REFERENCES VeneficiaCustomers (VeneficiaCustomerId)
        ON DELETE CASCADE,
    CONSTRAINT fk_VeneficiaWishlistItems_itemVariant
        FOREIGN KEY (itemVariantId) REFERENCES VeneficiaItemVariants (itemVariantId)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS VeneficiaReviews (
    customerId BIGINT UNSIGNED NOT NULL,
    itemVariantId BIGINT UNSIGNED NOT NULL,
    ordreId BIGINT UNSIGNED NULL,
    reviewScore TINYINT UNSIGNED NOT NULL,
    reviewedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (customerId, itemVariantId),
    CONSTRAINT fk_VeneficiaReviews_customer
        FOREIGN KEY (customerId) REFERENCES VeneficiaCustomers (VeneficiaCustomerId)
        ON DELETE CASCADE,
    CONSTRAINT fk_VeneficiaReviews_itemVariant
        FOREIGN KEY (itemVariantId) REFERENCES VeneficiaItemVariants (itemVariantId)
        ON DELETE CASCADE,
    CONSTRAINT fk_VeneficiaReviews_ordre
        FOREIGN KEY (ordreId) REFERENCES VeneficiaOrdre (ordreID)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS VeneficiaBasketItems (
    customerId BIGINT UNSIGNED NOT NULL,
    itemVariantId BIGINT UNSIGNED NOT NULL,
    amount INT UNSIGNED NOT NULL DEFAULT 1,
    timeAdded DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (customerId, itemVariantId),
    CONSTRAINT fk_VeneficiaBasketItems_customer
        FOREIGN KEY (customerId) REFERENCES VeneficiaCustomers (VeneficiaCustomerId)
        ON DELETE CASCADE,
    CONSTRAINT fk_VeneficiaBasketItems_itemVariant
        FOREIGN KEY (itemVariantId) REFERENCES VeneficiaItemVariants (itemVariantId)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS VeneficiaSessions (
    sessionID VARCHAR(128) NOT NULL,
    customerId BIGINT UNSIGNED NULL,
    employeeId BIGINT UNSIGNED NULL,
    createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ValidUntil DATETIME NOT NULL,
    PRIMARY KEY (sessionID),
    CONSTRAINT fk_VeneficiaSessions_customer
        FOREIGN KEY (customerId) REFERENCES VeneficiaCustomers (VeneficiaCustomerId)
        ON DELETE CASCADE,
    CONSTRAINT fk_VeneficiaSessions_employee
        FOREIGN KEY (employeeId) REFERENCES VeneficiaEmployees (employeeID)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS VeneficiaOrdreReturns (
    returnId BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ordreId BIGINT UNSIGNED NOT NULL,
    itemVariantId BIGINT UNSIGNED NOT NULL,
    amount INT UNSIGNED NOT NULL,
    returnReason TEXT NOT NULL,
    returnStatus VARCHAR(32) NOT NULL,
    refundAmount DECIMAL(10,2) NULL,
    requestedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processedAt DATETIME NULL,
    PRIMARY KEY (returnId),
    CONSTRAINT fk_VeneficiaOrdreReturns_ordre
        FOREIGN KEY (ordreId) REFERENCES VeneficiaOrdre (ordreID)
        ON DELETE CASCADE,
    CONSTRAINT fk_VeneficiaOrdreReturns_itemVariant
        FOREIGN KEY (itemVariantId) REFERENCES VeneficiaItemVariants (itemVariantId)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS VeneficiaOrdreItems (
    ordreId BIGINT UNSIGNED NOT NULL,
    itemVariantId BIGINT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    itemPrice DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (ordreId, itemVariantId),
    CONSTRAINT fk_VeneficiaOrdreItems_ordre
        FOREIGN KEY (ordreId) REFERENCES VeneficiaOrdre (ordreID)
        ON DELETE CASCADE,
    CONSTRAINT fk_VeneficiaOrdreItems_itemVariant
        FOREIGN KEY (itemVariantId) REFERENCES VeneficiaItemVariants (itemVariantId)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS VeneficiaItemAttachments (
    itemId BIGINT UNSIGNED NOT NULL,
    attachmentId BIGINT UNSIGNED NOT NULL,
    sortOrder INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (itemId, attachmentId),
    CONSTRAINT fk_VeneficiaItemAttachments_item
        FOREIGN KEY (itemId) REFERENCES VeneficiaItems (itemId)
        ON DELETE CASCADE,
    CONSTRAINT fk_VeneficiaItemAttachments_attachment
        FOREIGN KEY (attachmentId) REFERENCES VeneficiaAttachments (attachmentId)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;