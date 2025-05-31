-- Insert into Supplier Group
INSERT INTO `tabSupplier Group` (name, supplier_group_name, parent_supplier_group, creation, modified)
VALUES 
('SG-101', 'Hardware IT', 'All Supplier Groups', NOW(), NOW()),
('SG-102', 'Software Solutions', 'All Supplier Groups', NOW(), NOW()),
('SG-103', 'Consulting Services', 'All Supplier Groups', NOW(), NOW());

-- Insert into Item Group (for item categorization)
INSERT INTO `tabItem Group` (name, item_group_name, parent_item_group, creation, modified)
VALUES 
('IG-101', 'Hardware', 'All Item Groups', NOW(), NOW()),
('IG-102', 'Software', 'All Item Groups', NOW(), NOW()),
('IG-103', 'Services', 'All Item Groups', NOW(), NOW());

-- Insert into Item
INSERT INTO `tabItem` (name, item_code, item_name, item_group, stock_uom, is_stock_item, creation, modified)
VALUES 
('ITEM-101', 'HW-SRV-101', 'HP ProLiant Server', 'Hardware', 'Unit', 1, NOW(), NOW()),
('ITEM-102', 'SW-LIC-101', 'CRM Software License', 'Software', 'Unit', 0, NOW(), NOW()),
('ITEM-103', 'CONS-101', 'IT Consulting Service', 'Services', 'Hour', 0, NOW(), NOW());

-- Insert into Supplier
INSERT INTO `tabSupplier` (name, supplier_name, supplier_group, supplier_type, country, creation, modified)
VALUES 
('SUP-101', 'TechTrend Innovations', 'Hardware IT', 'Company', 'Germany', NOW(), NOW()),
('SUP-102', 'SoftPeak Solutions', 'Software Solutions', 'Company', 'United Kingdom', NOW(), NOW()),
('SUP-103', 'ConsultPro GmbH', 'Consulting Services', 'Company', 'Switzerland', NOW(), NOW());

-- Insert into Material Request
INSERT INTO `tabMaterial Request` (name, title, material_request_type, transaction_date, status, company, creation, modified)
VALUES 
('MR-101', 'Servers for Data Center Upgrade', 'Purchase', '2025-04-10', 'Pending', 'TechCorp', NOW(), NOW()),
('MR-102', 'CRM Software Licenses', 'Purchase', '2025-04-12', 'Submitted', 'TechCorp', NOW(), NOW()),
('MR-103', 'Consulting for System Integration', 'Purchase', '2025-04-15', 'Ordered', 'TechCorp', NOW(), NOW());

-- Insert into Material Request Item
INSERT INTO `tabMaterial Request Item` (name, parent, parentfield, parenttype, item_code, qty, schedule_date, creation, modified)
VALUES 
('MRI-101', 'MR-101', 'items', 'Material Request', 'HW-SRV-101', 10, '2025-05-01', NOW(), NOW()),
('MRI-102', 'MR-102', 'items', 'Material Request', 'SW-LIC-101', 20, '2025-05-05', NOW(), NOW()),
('MRI-103', 'MR-103', 'items', 'Material Request', 'CONS-101', 80, '2025-05-10', NOW(), NOW());

-- Insert into Supplier Quotation
INSERT INTO `tabSupplier Quotation` (name, supplier, transaction_date, valid_till, status, company, creation, modified)
VALUES 
('SQ-101', 'SUP-101', '2025-04-11', '2025-05-11', 'Draft', 'TechCorp', NOW(), NOW()),
('SQ-102', 'SUP-102', '2025-04-13', '2025-05-13', 'Submitted', 'TechCorp', NOW(), NOW()),
('SQ-103', 'SUP-103', '2025-04-16', '2025-05-16', 'Submitted', 'TechCorp', NOW(), NOW());

-- Insert into Supplier Quotation Item
INSERT INTO `tabSupplier Quotation Item` (name, parent, parentfield, parenttype, item_code, qty, rate, amount, creation, modified)
VALUES 
('SQI-101', 'SQ-101', 'items', 'Supplier Quotation', 'HW-SRV-101', 10, 3000.00, 30000.00, NOW(), NOW()),
('SQI-102', 'SQ-102', 'items', 'Supplier Quotation', 'SW-LIC-101', 20, 150.00, 3000.00, NOW(), NOW()),
('SQI-103', 'SQ-103', 'items', 'Supplier Quotation', 'CONS-101', 80, 90.00, 7200.00, NOW(), NOW());

-- Insert into Purchase Order
INSERT INTO `tabPurchase Order` (name, supplier, transaction_date, schedule_date, status, company, creation, modified)
VALUES 
('PO-101', 'SUP-101', '2025-04-15', '2025-05-05', 'To Receive and Bill', 'TechCorp', NOW(), NOW()),
('PO-102', 'SUP-102', '2025-04-17', '2025-05-10', 'To Receive and Bill', 'TechCorp', NOW(), NOW()),
('PO-103', 'SUP-103', '2025-04-20', '2025-05-15', 'To Receive and Bill', 'TechCorp', NOW(), NOW());

-- Insert into Purchase Order Item
INSERT INTO `tabPurchase Order Item` (name, parent, parentfield, parenttype, item_code, qty, rate, amount, schedule_date, creation, modified)
VALUES 
('POI-101', 'PO-101', 'items', 'Purchase Order', 'HW-SRV-101', 10, 2950.00, 29500.00, '2025-05-05', NOW(), NOW()),
('POI-102', 'PO-102', 'items', 'Purchase Order', 'SW-LIC-101', 20, 145.00, 2900.00, '2025-05-10', NOW(), NOW()),
('POI-103', 'PO-103', 'items', 'Purchase Order', 'CONS-101', 80, 85.00, 6800.00, '2025-05-15', NOW(), NOW());

-- Insert into Purchase Receipt
INSERT INTO `tabPurchase Receipt` (name, supplier, posting_date, status, company, creation, modified)
VALUES 
('PR-101', 'SUP-101', '2025-05-01', 'Completed', 'TechCorp', NOW(), NOW()),
('PR-102', 'SUP-102', '2025-05-05', 'Completed', 'TechCorp', NOW(), NOW()),
('PR-103', 'SUP-103', '2025-05-10', 'Completed', 'TechCorp', NOW(), NOW());

-- Insert into Purchase Receipt Item
INSERT INTO `tabPurchase Receipt Item` (name, parent, parentfield, parenttype, item_code, qty, rate, amount, creation, modified)
VALUES 
('PRI-101', 'PR-101', 'items', 'Purchase Receipt', 'HW-SRV-101', 10, 2950.00, 29500.00, NOW(), NOW()),
('PRI-102', 'PR-102', 'items', 'Purchase Receipt', 'SW-LIC-101', 20, 145.00, 2900.00, NOW(), NOW()),
('PRI-103', 'PR-103', 'items', 'Purchase Receipt', 'CONS-101', 80, 85.00, 6800.00, NOW(), NOW());

-- Insert into Purchase Invoice
INSERT INTO `tabPurchase Invoice` (name, supplier, posting_date, due_date, status, company, creation, modified)
VALUES 
('PINV-101', 'SUP-101', '2025-05-03', '2025-06-03', 'Paid', 'TechCorp', NOW(), NOW()),
('PINV-102', 'SUP-102', '2025-05-07', '2025-06-07', 'Unpaid', 'TechCorp', NOW(), NOW()),
('PINV-103', 'SUP-103', '2025-05-12', '2025-06-12', 'Unpaid', 'TechCorp', NOW(), NOW());

-- Insert into Purchase Invoice Item
INSERT INTO `tabPurchase Invoice Item` (name, parent, parentfield, parenttype, item_code, qty, rate, amount, creation, modified)
VALUES 
('PIVI-101', 'PINV-101', 'items', 'Purchase Invoice', 'HW-SRV-101', 10, 2950.00, 29500.00, NOW(), NOW()),
('PIVI-102', 'PINV-102', 'items', 'Purchase Invoice', 'SW-LIC-101', 20, 145.00, 2900.00, NOW(), NOW()),
('PIVI-103', 'PINV-103', 'items', 'Purchase Invoice', 'CONS-101', 80, 85.00, 6800.00, NOW(), NOW());

-- Insert into Payment Entry
INSERT INTO `tabPayment Entry` (name, payment_type, posting_date, party_type, party, paid_amount, status, company, creation, modified)
VALUES 
('PE-101', 'Pay', '2025-05-04', 'Supplier', 'SUP-101', 29500.00, 'Submitted', 'TechCorp', NOW(), NOW());

-- Insert into Quotation (Customer)
INSERT INTO `tabQuotation` (name, quotation_to, party_name, transaction_date, valid_till, order_type, status, company, creation, modified)
VALUES 
('QTN-101', 'Customer', 'CUST-101', '2025-04-20', '2025-05-20', 'Sales', 'Submitted', 'TechCorp', NOW(), NOW()),
('QTN-102', 'Customer', 'CUST-102', '2025-04-22', '2025-05-22', 'Sales', 'Open', 'TechCorp', NOW(), NOW()),
('QTN-103', 'Customer', 'CUST-103', '2025-04-25', '2025-05-25', 'Sales', 'Open', 'TechCorp', NOW(), NOW());

-- Insert into Quotation Item
INSERT INTO `tabQuotation Item` (name, parent, parentfield, parenttype, item_code, qty, rate, amount, creation, modified)
VALUES 
('QTI-101', 'QTN-101', 'items', 'Quotation', 'HW-SRV-101', 3, 3500.00, 10500.00, NOW(), NOW()),
('QTI-102', 'QTN-102', 'items', 'Quotation', 'SW-LIC-101', 10, 200.00, 2000.00, NOW(), NOW()),
('QTI-103', 'QTN-103', 'items', 'Quotation', 'CONS-101', 40, 120.00, 4800.00, NOW(), NOW());
-- Taxes et frais pour les devis clients
INSERT INTO `tabSales Taxes and Charges` (name, parent, parentfield, parenttype, charge_type, account_head, description, rate, tax_amount, creation, modified)
VALUES 
('ST-001', 'QTN-001', 'taxes', 'Quotation', 'On Net Total', 'TVA 20%', 'TVA', 20, 1280, NOW(), NOW()),
('ST-002', 'QTN-002', 'taxes', 'Quotation', 'On Net Total', 'TVA 20%', 'TVA', 20, 250, NOW(), NOW()),
('ST-003', 'QTN-003', 'taxes', 'Quotation', 'On Net Total', 'TVA 20%', 'TVA', 20, 380, NOW(), NOW());
-- Données clients (Customer)
INSERT INTO `tabCustomer` (name, customer_name, customer_type, customer_group, territory, creation, modified)
VALUES 
('CUST-001', 'Société Alpha', 'Company', 'Commercial', 'France', NOW(), NOW()),
('CUST-002', 'Entreprise Beta', 'Company', 'Commercial', 'Belgique', NOW(), NOW()),
('CUST-003', 'Organisation Gamma', 'Company', 'Government', 'Suisse', NOW(), NOW());

-- Coordonnées des clients (Customer Address)
INSERT INTO `tabAddress` (name, address_title, address_type, address_line1, city, state, country, is_primary_address, creation, modified)
VALUES 
('ADDR-001', 'Société Alpha-Siège', 'Billing', '15 Rue de la Paix', 'Paris', 'Île-de-France', 'France', 1, NOW(), NOW()),
('ADDR-002', 'Entreprise Beta-Siège', 'Billing', '25 Avenue Louise', 'Bruxelles', 'Bruxelles-Capitale', 'Belgique', 1, NOW(), NOW()),
('ADDR-003', 'Organisation Gamma-Siège', 'Billing', '10 Rue du Rhône', 'Genève', 'Genève', 'Suisse', 1, NOW(), NOW());

-- Liens entre clients et adresses
INSERT INTO `tabDynamic Link` (name, link_doctype, link_name, parent, parentfield, parenttype, creation, modified)
VALUES 
('DL-001', 'Customer', 'CUST-001', 'ADDR-001', 'links', 'Address', NOW(), NOW()),
('DL-002', 'Customer', 'CUST-002', 'ADDR-002', 'links', 'Address', NOW(), NOW()),
('DL-003', 'Customer', 'CUST-003', 'ADDR-003', 'links', 'Address', NOW(), NOW());