-- =============================================================================
-- Go2My.Link — Payment & Subscription Tables
-- =============================================================================
-- Subscription management, payment records, and discount rules.
-- Note: tblSubscriptionTiers is created in 011_core_subscription_tiers.sql
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    0.2.0
-- @since      Phase 1
-- =============================================================================

USE `mwtools_Go2MyLink`;

-- =============================================================================
-- Subscriptions (active subscriptions per org)
-- =============================================================================
CREATE TABLE IF NOT EXISTS `tblSubscriptions` (
    `subscriptionUID`       BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,

    `orgHandle`             VARCHAR(50)         NOT NULL
        COMMENT 'FK to tblOrganisations.orgHandle',

    `tierID`                VARCHAR(50)         NOT NULL
        COMMENT 'FK to tblSubscriptionTiers.tierID',

    `billingCycle`          ENUM('monthly', 'annual', 'lifetime')
                            NOT NULL DEFAULT 'monthly'
        COMMENT 'Billing cycle',

    `status`                ENUM('active', 'past_due', 'cancelled', 'expired', 'trial')
                            NOT NULL DEFAULT 'active'
        COMMENT 'Current subscription status',

    `paymentProvider`       VARCHAR(50)         DEFAULT NULL
        COMMENT 'Payment provider (paypal, apple_pay, google_pay, crypto)',

    `providerSubscriptionID` VARCHAR(255)       DEFAULT NULL
        COMMENT 'Subscription ID from the payment provider',

    `currentPeriodStart`    DATETIME            NOT NULL
        COMMENT 'Start of current billing period',

    `currentPeriodEnd`      DATETIME            NOT NULL
        COMMENT 'End of current billing period',

    `trialEndsAt`           DATETIME            DEFAULT NULL
        COMMENT 'When the trial period ends (if applicable)',

    `cancelledAt`           DATETIME            DEFAULT NULL
        COMMENT 'When the subscription was cancelled',

    `cancelReason`          TEXT                DEFAULT NULL
        COMMENT 'Reason for cancellation',

    `createdAt`             DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt`             DATETIME            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`subscriptionUID`),
    INDEX `IDX_sub_org` (`orgHandle`),
    INDEX `IDX_sub_tier` (`tierID`),
    INDEX `IDX_sub_status` (`status`),
    INDEX `IDX_sub_period_end` (`currentPeriodEnd`),

    CONSTRAINT `FK_sub_org`
        FOREIGN KEY (`orgHandle`)
        REFERENCES `tblOrganisations` (`orgHandle`)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT `FK_sub_tier`
        FOREIGN KEY (`tierID`)
        REFERENCES `tblSubscriptionTiers` (`tierID`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Active subscriptions per organisation';

-- =============================================================================
-- Payments (transaction records)
-- =============================================================================
CREATE TABLE IF NOT EXISTS `tblPayments` (
    `paymentUID`            BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,

    `subscriptionUID`       BIGINT UNSIGNED     DEFAULT NULL
        COMMENT 'FK to tblSubscriptions.subscriptionUID (NULL for one-time)',

    `orgHandle`             VARCHAR(50)         NOT NULL
        COMMENT 'FK to tblOrganisations.orgHandle',

    `paymentProvider`       VARCHAR(50)         NOT NULL
        COMMENT 'Payment provider used',

    `providerTransactionID` VARCHAR(255)        DEFAULT NULL
        COMMENT 'Transaction ID from the payment provider',

    `amount`                DECIMAL(10, 2)      NOT NULL
        COMMENT 'Payment amount',

    `currency`              CHAR(3)             NOT NULL DEFAULT 'GBP'
        COMMENT 'ISO 4217 currency code',

    `discountAmount`        DECIMAL(10, 2)      NOT NULL DEFAULT 0.00
        COMMENT 'Discount amount applied',

    `discountUID`           BIGINT UNSIGNED     DEFAULT NULL
        COMMENT 'FK to tblPaymentDiscounts.discountUID',

    `status`                ENUM('pending', 'completed', 'failed', 'refunded', 'disputed')
                            NOT NULL DEFAULT 'pending'
        COMMENT 'Payment status',

    `paymentType`           ENUM('subscription', 'one_time', 'refund')
                            NOT NULL DEFAULT 'subscription',

    `invoiceNumber`         VARCHAR(50)         DEFAULT NULL
        COMMENT 'Generated invoice number',

    `receiptURL`            VARCHAR(500)        DEFAULT NULL
        COMMENT 'URL to payment receipt (from provider)',

    `providerData`          JSON                DEFAULT NULL
        COMMENT 'Raw response data from payment provider',

    `paidAt`                DATETIME            DEFAULT NULL
        COMMENT 'When payment was confirmed',

    `createdAt`             DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt`             DATETIME            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`paymentUID`),
    INDEX `IDX_payment_sub` (`subscriptionUID`),
    INDEX `IDX_payment_org` (`orgHandle`),
    INDEX `IDX_payment_status` (`status`),
    INDEX `IDX_payment_created` (`createdAt`),
    INDEX `IDX_payment_invoice` (`invoiceNumber`),

    CONSTRAINT `FK_payment_sub`
        FOREIGN KEY (`subscriptionUID`)
        REFERENCES `tblSubscriptions` (`subscriptionUID`)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    CONSTRAINT `FK_payment_org`
        FOREIGN KEY (`orgHandle`)
        REFERENCES `tblOrganisations` (`orgHandle`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Payment transaction records';

-- =============================================================================
-- Payment Discounts (per payment method)
-- =============================================================================
CREATE TABLE IF NOT EXISTS `tblPaymentDiscounts` (
    `discountUID`           BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,

    `discountCode`          VARCHAR(50)         DEFAULT NULL
        COMMENT 'Promo/discount code (NULL for automatic discounts)',

    `discountName`          VARCHAR(255)        NOT NULL
        COMMENT 'Display name',

    `discountType`          ENUM('percentage', 'fixed_amount')
                            NOT NULL DEFAULT 'percentage',

    `discountValue`         DECIMAL(10, 2)      NOT NULL
        COMMENT 'Percentage or fixed amount',

    `applicableProvider`    VARCHAR(50)         DEFAULT NULL
        COMMENT 'Restrict to specific payment provider (NULL = all)',

    `applicableTier`        VARCHAR(50)         DEFAULT NULL
        COMMENT 'Restrict to specific tier (NULL = all)',

    `maxUses`               INT UNSIGNED        DEFAULT NULL
        COMMENT 'Maximum total uses (NULL = unlimited)',

    `currentUses`           INT UNSIGNED        NOT NULL DEFAULT 0
        COMMENT 'Current number of uses',

    `validFrom`             DATETIME            DEFAULT NULL,
    `validUntil`            DATETIME            DEFAULT NULL,

    `isActive`              TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `createdAt`             DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt`             DATETIME            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`discountUID`),
    UNIQUE KEY `UQ_discount_code` (`discountCode`),
    INDEX `IDX_discount_active` (`isActive`, `validFrom`, `validUntil`)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Payment discounts per payment method or promo code';
