Je vais vous fournir un guide complet et détaillé regroupant toutes les étapes que vous avez suivies pour générer les données actuelles dans ERPNext, avec les résultats obtenus à l’étape 11 (vérification des statuts). Ce guide inclura également les **débogages** pour les problèmes rencontrés (comme le "Limit Crossed" à l’étape 7 ou le "Missing Account" à l’étape 10), ainsi que des **variantes** pour créer d’autres données similaires avec des ajustements. Cela vous permettra de reproduire ce processus ou de générer des données supplémentaires pour d’autres tests dans votre application.

---

## Guide complet : Création de données d’achat dans ERPNext

### Objectif
Créer des données dans ERPNext pour tester une application avec les fonctionnalités suivantes :
1. Lister les **Supplier Quotations** et leurs prix.
2. Lister les **Purchase Orders** avec leurs statuts (reçu, non reçu).
3. Lister les **Purchase Invoices** avec leurs statuts (à payer, payé).

### Résultat attendu (Étape 11)
- **Supplier Quotations** : 2 devis (820 USD et 750 USD pour `TechSupplier SARL`).
- **Purchase Orders** : 1 "reçu" (3 unités, 820 USD), 1 "non reçu" (2 unités, 750 USD).
- **Purchase Invoices** : 1 "Unpaid" (2460 USD), 1 "Paid" (1500 USD).

---

### Étapes détaillées

#### 🧩 Étape 1 : Création de produit (**Item**)
**Objectif** : Créer un article qui sera utilisé dans les transactions d’achat.
1. **Aller dans le module `Stock`** : Cliquez sur **"Stock"**.
2. **Accéder à la liste des articles** : Cliquez sur **"Item"**.
3. **Créer un nouvel article** : Cliquez sur **"New"**.
4. **Remplir le formulaire** :
   - **Item Code** : `DELL-LAPTOP`
   - **Item Name** : `Ordinateur Portable Dell`
   - **Item Group** : `Finished Goods` (ou un groupe approprié)
   - **Stock UOM** : `Nos` (unité de mesure : unités)
   - **Default Warehouse** : `Stores - ZC` (ou `Stores - [Code de votre société]`)
5. **Cocher les options** :
   - ✅ **Maintain Stock** (permet de suivre le stock)
   - ✅ **Is Purchase Item** (indique que l’article peut être acheté)
6. **Sauvegarder** : Cliquez sur **"Save"**.

**Résultat** : L’article `DELL-LAPTOP` est créé et prêt à être utilisé.

**Débogage** :
- **Problème** : Si le champ **Default Warehouse** n’est pas disponible ou incorrect :
  - Vérifiez que le warehouse existe dans **Stock > Warehouse**.
  - Si absent, créez un warehouse : **Stock > Warehouse > New**, nommez-le `Stores - ZC`, et associez-le à votre entreprise (`Zo Camp` ou `Ta société`).

**Variante** : Pour un autre produit (par exemple, des imprimantes) :
- **Item Code** : `HP-PRINTER`
- **Item Name** : `Imprimante HP`
- **Item Group** : `Finished Goods`
- **Stock UOM** : `Nos`
- **Default Warehouse** : `Stores - ZC`

---

#### 🧩 Étape 2 : Création d’un fournisseur (**Supplier**)
**Objectif** : Créer un fournisseur pour les transactions d’achat.
1. **Aller dans le module `Buying`** : Cliquez sur **"Buying"**.
2. **Accéder à la liste des fournisseurs** : Cliquez sur **"Supplier"**.
3. **Créer un nouveau fournisseur** : Cliquez sur **"New"**.
4. **Remplir le formulaire** :
   - **Supplier Name** : `TechSupplier SARL`
   - **Supplier Type** : `Company`
   - **Supplier Group** : `Local` (ou un groupe approprié)
   - **Default Currency** : `USD`
5. **Sauvegarder** : Cliquez sur **"Save"**.

**Résultat** : Le fournisseur `TechSupplier SARL` est créé.

**Débogage** :
- **Problème** : Si le champ **Default Currency** n’est pas disponible :
  - Vérifiez que la devise `USD` est activée dans **Setup > Currency**.
  - Si absent, activez `USD` ou choisissez une autre devise.

**Variante** : Pour un autre fournisseur :
- **Supplier Name** : `GlobalTech Inc`
- **Supplier Type** : `Company`
- **Supplier Group** : `Local`
- **Default Currency** : `USD`

---

#### 🧩 Étape 3 : Création d’une demande de besoin (**Material Request**)
**Objectif** : Créer une demande d’achat pour 5 unités de `DELL-LAPTOP`.
1. **Aller dans le module `Stock`** : Cliquez sur **"Stock"**.
2. **Ouvrir la section `Material Request`** : Cliquez sur **"Material Request"**.
3. **Créer une nouvelle demande** : Cliquez sur **"New"**.
4. **Remplir les informations générales** :
   - **Material Request Type** : `Purchase`
   - **Transaction Date** : 2025-05-02
   - **Schedule Date** : 2025-05-09
   - **Company** : `Zo Camp` (ou `Ta société`, selon votre configuration)
5. **Ajouter le produit** :
   - **Item Code** : `DELL-LAPTOP`
   - **Qty** : `5`
   - **Warehouse** : `Stores - ZC`
6. **Sauvegarder et soumettre** : Cliquez sur **"Save"**, puis **"Submit"**.

**Résultat** : Une **Material Request** pour 5 `DELL-LAPTOP` est créée et soumise.

**Débogage** :
- **Problème** : Si l’article `DELL-LAPTOP` n’apparaît pas :
  - Vérifiez que l’article existe et que **Is Purchase Item** est coché dans **Stock > Item**.
- **Problème** : Si le warehouse n’est pas disponible :
  - Créez un warehouse dans **Stock > Warehouse** comme indiqué dans l’étape 1.

**Variante** : Pour une autre demande (par exemple, pour `HP-PRINTER`) :
- **Material Request Type** : `Purchase`
- **Transaction Date** : 2025-05-02
- **Schedule Date** : 2025-05-09
- **Company** : `Zo Camp`
- **Item Code** : `HP-PRINTER`
- **Qty** : `3`
- **Warehouse** : `Stores - ZC`

---

#### 🧩 Étape 4 : Création d’une demande de devis (**Request for Quotation**)
**Objectif** : Créer une RFQ basée sur la **Material Request**.
1. **Aller dans le module `Buying`** : Cliquez sur **"Buying"**.
2. **Ouvrir la section `Request for Quotation`** : Cliquez sur **"Request for Quotation"**.
3. **Créer une nouvelle RFQ** : Cliquez sur **"New"**.
4. **Remplir les informations générales** :
   - **Company** : `Zo Camp`
   - **Required Date** : 2025-05-10
5. **Ajouter le fournisseur** :
   - Dans **Suppliers**, cliquez sur **"Add Row"**.
   - Sélectionnez `TechSupplier SARL`.
6. **Ajouter les produits** :
   - Cliquez sur **"Get Items from" > "Material Request"**.
   - Sélectionnez la **Material Request** créée à l’étape 3 (5 `DELL-LAPTOP`).
7. **Sauvegarder et soumettre** : Cliquez sur **"Save"**, puis **"Submit"**.

**Résultat** : Une **Request for Quotation** est créée avec 5 `DELL-LAPTOP` pour `TechSupplier SARL`.

**Débogage** :
- **Problème** : Si la **Material Request** n’apparaît pas dans **Get Items from** :
  - Vérifiez qu’elle est soumise (**Status** : Submitted) dans **Stock > Material Request**.

**Variante** : Pour une autre RFQ (par exemple, pour `GlobalTech Inc`) :
- Ajoutez `GlobalTech Inc` comme fournisseur dans la section **Suppliers**.
- Utilisez une autre **Material Request** (par exemple, celle pour `HP-PRINTER`).

---

#### 🧩 Étape 5 : Création de deux devis fournisseurs (**Supplier Quotation**)
**Objectif** : Créer deux devis pour `TechSupplier SARL` avec des prix différents.

**Première Supplier Quotation** :
1. **Aller dans le module `Buying`** : Cliquez sur **"Buying"**.
2. **Ouvrir la section `Supplier Quotation`** : Cliquez sur **"Supplier Quotation"**.
3. **Créer une nouvelle quotation** : Cliquez sur **"New"**.
4. **Remplir les informations générales** :
   - **Supplier** : `TechSupplier SARL`
   - **Quotation Date** : 2025-05-02
   - **Valid Till** : 2025-05-09
   - **Company** : `Zo Camp`
5. **Ajouter les produits** :
   - Cliquez sur **"Get Items from" > "Request for Quotation"**.
   - Sélectionnez la **RFQ** de l’étape 4.
6. **Remplir les prix** :
   - **Item Code** : `DELL-LAPTOP`
   - **Qty** : 5
   - **Rate** : 800 USD
   - **Amount** : 4000 USD
7. **Sauvegarder et soumettre** : Cliquez sur **"Save"**, puis **"Submit"**.

**Deuxième Supplier Quotation** :
1. Répétez les étapes 1 à 5 ci-dessus.
2. **Remplir les prix** :
   - **Item Code** : `DELL-LAPTOP`
   - **Qty** : 5
   - **Rate** : 750 USD
   - **Amount** : 3750 USD
3. **Sauvegarder et soumettre** : Cliquez sur **"Save"**, puis **"Submit"**.

**Résultat** : Deux **Supplier Quotations** sont créées : une à 800 USD, une à 750 USD.

**Débogage** :
- **Problème** : Si la **RFQ** n’apparaît pas dans **Get Items from** :
  - Vérifiez que la **RFQ** est soumise (**Status** : Submitted) dans **Buying > Request for Quotation**.

**Variante** : Pour un autre devis (par exemple, pour `GlobalTech Inc`) :
- **Supplier** : `GlobalTech Inc`
- **Quotation Date** : 2025-05-02
- **Valid Till** : 2025-05-09
- **Company** : `Zo Camp`
- **Item Code** : `HP-PRINTER`
- **Qty** : 3
- **Rate** : 300 USD
- **Amount** : 900 USD

---

#### 🧩 Étape 6 : Mise à jour des prix dans une **Supplier Quotation**
**Objectif** : Simuler une mise à jour de prix en créant une nouvelle **Supplier Quotation** (car l’option "Amend" n’est pas disponible).
1. **Créer une nouvelle Supplier Quotation avec le prix mis à jour** :
   - Allez dans **Buying > Supplier Quotation**.
   - Cliquez sur **"New"**.
   - Remplissez les informations :
     - **Supplier** : `TechSupplier SARL`
     - **Quotation Date** : 2025-05-02
     - **Valid Till** : 2025-05-09
     - **Company** : `Zo Camp`
   - Ajoutez les produits :
     - Cliquez sur **"Get Items from" > "Request for Quotation"**.
     - Sélectionnez la **RFQ** de l’étape 4.
   - Mettez à jour le prix :
     - **Item Code** : `DELL-LAPTOP`
     - **Qty** : 5
     - **Rate** : 820 USD
     - **Amount** : 4100 USD
   - Cliquez sur **"Save"**, puis **"Submit"**.

**Résultat** : Une nouvelle **Supplier Quotation** à 820 USD est créée, remplaçant celle à 800 USD.

**Débogage** :
- **Problème** : Si vous souhaitez annuler l’ancienne quotation (800 USD) mais qu’elle est liée à un **Purchase Order** :
  - Annulez d’abord le **Purchase Order** (étape 7, si déjà créé).
  - Annulez ensuite la **Supplier Quotation** (cliquez sur **"Cancel"**).

**Variante** : Pour un autre devis mis à jour :
- Mettez à jour le devis de `GlobalTech Inc` (par exemple, passez de 300 USD à 320 USD par unité pour `HP-PRINTER`).

---

#### 🧩 Étape 7 : Création de deux bons de commande (**Purchase Order**)
**Objectif** : Créer deux **Purchase Orders** avec des quantités ajustées pour éviter le "Limit Crossed" (total de 5 unités, correspondant à la **Material Request**).

**Premier Purchase Order** :
1. **Aller dans le module `Buying`** : Cliquez sur **"Buying"**.
2. **Ouvrir la section `Purchase Order`** : Cliquez sur **"Purchase Order"**.
3. **Créer un nouveau `Purchase Order`** : Cliquez sur **"New"**.
4. **Remplir les informations générales** :
   - **Supplier** : `TechSupplier SARL`
   - **Order Date** : 2025-05-02
   - **Delivery Date** : 2025-05-09
   - **Company** : `Zo Camp`
   - **Currency** : `USD`
5. **Ajouter les produits** :
   - Cliquez sur **"Get Items from" > "Supplier Quotation"**.
   - Sélectionnez la **Supplier Quotation** à 820 USD.
   - Modifiez la quantité :
     - **Item Code** : `DELL-LAPTOP`
     - **Qty** : 3 (au lieu de 5)
     - **Rate** : 820 USD
     - **Amount** : 2460 USD
     - **Warehouse** : `Stores - ZC`
6. **Sauvegarder et soumettre** : Cliquez sur **"Save"**, puis **"Submit"**.

**Deuxième Purchase Order** :
1. Répétez les étapes 1 à 5 ci-dessus.
2. **Ajouter les produits** :
   - Cliquez sur **"Get Items from" > "Supplier Quotation"**.
   - Sélectionnez la **Supplier Quotation** à 750 USD.
   - Modifiez la quantité :
     - **Item Code** : `DELL-LAPTOP`
     - **Qty** : 2 (total 3 + 2 = 5, respectant la limite)
     - **Rate** : 750 USD
     - **Amount** : 1500 USD
     - **Warehouse** : `Stores - ZC`
3. **Sauvegarder et soumettre** : Cliquez sur **"Save"**, puis **"Submit"**.

**Résultat** : Deux **Purchase Orders** sont créés : un à 2460 USD (3 unités), un à 1500 USD (2 unités).

**Débogage** : **Popup "Limit Crossed"**
- **Problème** : Si vous essayez de commander plus de 5 unités au total (limite de la **Material Request**), vous verrez :
  > **Limit Crossed**  
  > This document is over limit by Stock Qty 5.0 for item DELL-LAPTOP.
- **Solution 1** : Ajustez les quantités comme ci-dessus (3 + 2 = 5).
- **Solution 2** : Autorisez un dépassement :
  - Allez dans **Setup > Stock Settings**.
  - Mettez **Over Receipt/Delivery Allowance** à 100 %.
  - Sauvegardez.
  - Retentez la création des **Purchase Orders** avec les quantités originales (5 + 5).
- **Solution 3** : Créez une nouvelle **Material Request** pour des unités supplémentaires (voir variante ci-dessous).

**Variante** : Pour un autre **Purchase Order** (par exemple, avec `GlobalTech Inc`) :
- Créez une nouvelle **Material Request** pour `HP-PRINTER` (étape 3 variante).
- Créez une nouvelle **RFQ** et **Supplier Quotation** (étapes 4 et 5 variantes).
- Créez un **Purchase Order** :
  - **Supplier** : `GlobalTech Inc`
  - **Qty** : 3
  - **Rate** : 320 USD
  - **Amount** : 960 USD

---

#### 🧩 Étape 8 : Création d’un reçu d’achat (**Purchase Receipt**)
**Objectif** : Marquer le premier **Purchase Order** (3 unités) comme "reçu".
1. **Aller dans le module `Stock`** : Cliquez sur **"Stock"**.
2. **Ouvrir la section `Purchase Receipt`** : Cliquez sur **"Purchase Receipt"**.
3. **Créer un nouveau `Purchase Receipt`** : Cliquez sur **"New"**.
4. **Remplir les informations générales** :
   - **Supplier** : `TechSupplier SARL`
   - **Posting Date** : 2025-05-03
   - **Company** : `Zo Camp`
5. **Ajouter les produits** :
   - Cliquez sur **"Get Items from" > "Purchase Order"**.
   - Sélectionnez le premier **Purchase Order** (820 USD, 3 unités).
6. **Vérifier les détails** :
   - **Qty** : 3
   - **Rate** : 820 USD
   - **Warehouse** : `Stores - ZC`
7. **Sauvegarder et soumettre** : Cliquez sur **"Save"**, puis **"Submit"**.

**Résultat** : Le premier **Purchase Order** est marqué comme "reçu".

**Débogage** :
- **Problème** : Si le **Purchase Order** n’apparaît pas dans **Get Items from** :
  - Vérifiez qu’il est soumis (**Status** : Submitted) dans **Buying > Purchase Order**.

**Variante** : Pour un autre **Purchase Receipt** :
- Créez un **Purchase Receipt** pour le **Purchase Order** de `GlobalTech Inc` (960 USD, 3 `HP-PRINTER`).

---

#### 🧩 Étape 9 : Création de deux factures d’achat (**Purchase Invoice**)
**Objectif** : Créer deux factures, une pour chaque **Purchase Order**.

**Première Purchase Invoice (non payée)** :
1. **Aller dans le module `Accounts`** : Cliquez sur **"Accounts"**.
2. **Ouvrir la section `Purchase Invoice`** : Cliquez sur **"Purchase Invoice"**.
3. **Créer une nouvelle `Purchase Invoice`** : Cliquez sur **"New"**.
4. **Remplir les informations générales** :
   - **Supplier** : `TechSupplier SARL`
   - **Posting Date** : 2025-05-03
   - **Due Date** : 2025-05-17
   - **Company** : `Zo Camp`
   - **Currency** : `USD`
5. **Ajouter les produits** :
   - Cliquez sur **"Get Items from" > "Purchase Order"**.
   - Sélectionnez le premier **Purchase Order** (820 USD, 3 unités).
6. **Vérifier les détails** :
   - **Qty** : 3
   - **Rate** : 820 USD
   - **Amount** : 2460 USD
   - **Warehouse** : `Stores - ZC`
7. **Sauvegarder et soumettre** : Cliquez sur **"Save"**, puis **"Submit"**.

**Deuxième Purchase Invoice (sera payée)** :
1. Répétez les étapes 1 à 5 ci-dessus.
2. **Ajouter les produits** :
   - Cliquez sur **"Get Items from" > "Purchase Order"**.
   - Sélectionnez le deuxième **Purchase Order** (750 USD, 2 unités).
3. **Vérifier les détails** :
   - **Qty** : 2
   - **Rate** : 750 USD
   - **Amount** : 1500 USD
   - **Warehouse** : `Stores - ZC`
4. **Sauvegarder et soumettre** : Cliquez sur **"Save"**, puis **"Submit"**.

**Résultat** : Deux **Purchase Invoices** sont créées : une à 2460 USD (non payée), une à 1500 USD (sera payée).

**Débogage** :
- **Problème** : Si le **Purchase Order** n’apparaît pas :
  - Vérifiez qu’il est soumis dans **Buying > Purchase Order**.

**Variante** : Pour une autre facture :
- Créez une **Purchase Invoice** pour le **Purchase Order** de `GlobalTech Inc` (960 USD, 3 `HP-PRINTER`).

---

#### 🧩 Étape 10 : Création d’un paiement (**Payment Entry**)
**Objectif** : Payer la deuxième **Purchase Invoice** (1500 USD).

1. **Configurer le Mode of Payment** (si non déjà fait) :
   - Allez dans **Accounts > Mode of Payment**.
   - Ouvrez **"Bank Draft"**.
   - Ajoutez un compte par défaut :
     - **Company** : `Zo Camp`
     - **Default Account** : `Company Bank - Zo Camp - ZC` (ou un autre compte bancaire)
   - Cliquez sur **"Save"**.

2. **Créer le Payment Entry** :
   - Allez dans **Accounts > Payment Entry**.
   - Cliquez sur **"New"**.
   - Remplissez les informations générales :
     - **Series** : `ACC-PAY-.YYYY-.`
     - **Payment Type** : `Pay`
     - **Posting Date** : 2025-05-04
     - **Mode of Payment** : `Bank Draft`
     - **Party Type** : `Supplier`
     - **Party** : `TechSupplier SARL`
     - **Party Name** : `TechSupplier SARL`
     - **Company** : `Zo Camp`
     - **Account Paid From** : `Company Bank - Zo Camp - ZC` (devrait être automatique)
     - **Account Paid To** : `Creditors - ZC`
     - **Account Currency** : `USD`
   - **Paid Amount (USD)** : `1500`
   - **Cheque/Reference No** : `CHK-001` (ou un numéro unique)
   - **Cheque/Reference Date** : `2025-05-04`

3. **Lier la facture** :
   - Dans la section **References**, cliquez sur **"Add Row"** (si disponible) :
     - **Reference Type** : `Purchase Invoice`
     - **Reference Name** : Sélectionnez la facture de 1500 USD.
     - **Amount** : `1500`
   - Si la section **References** n’apparaît pas, utilisez **"Get Outstanding Invoices"** (si disponible) ou soumettez et ajustez manuellement.

4. **Sauvegarder et soumettre** :
   - Cliquez sur **"Save"**.
   - Cliquez sur **"Submit"**.

**Résultat** : La deuxième **Purchase Invoice** (1500 USD) est marquée comme **Paid**.

**Débogage** : **Popup "Missing Account"**
- **Problème** : Si vous voyez :
  > **Missing Account**  
  > Please set default Cash or Bank account in Mode of Payment Bank Draft
- **Solution** :
  - Configurez un compte par défaut pour "Bank Draft" (comme indiqué ci-dessus).
  - Retentez la création du **Payment Entry**.

**Débogage** : **Section References absente**
- **Problème** : Si la section **References** n’apparaît pas :
  - Remplissez tous les champs obligatoires (**Paid Amount**, **Cheque/Reference No**, **Date**).
  - Si cela ne fonctionne pas, soumettez le **Payment Entry** et liez la facture manuellement après (si permis).

**Variante** : Pour un autre paiement :
- Payez une facture de `GlobalTech Inc` (par exemple, 960 USD pour `HP-PRINTER`).

---

#### 🧩 Étape 11 : Vérification des statuts
**Objectif** : Confirmer que les données sont correctes pour tester votre application.
1. **Lister les Supplier Quotations** :
   - **Buying > Supplier Quotation**, filtrez par `TechSupplier SARL`.
   - Résultat : 820 USD et 750 USD.
2. **Lister les Purchase Orders** :
   - **Buying > Purchase Order**, filtrez par `TechSupplier SARL`.
   - Résultat : Un "reçu" (3 unités, 820 USD), un "non reçu" (2 unités, 750 USD).
3. **Lister les Purchase Invoices** :
   - **Accounts > Purchase Invoice**, filtrez par `TechSupplier SARL`.
   - Résultat : Une "Unpaid" (2460 USD), une "Paid" (1500 USD).

**Résultat** : Les données sont prêtes pour tester votre application.

**Débogage** :
- **Problème** : Si une facture n’est pas au bon statut :
  - Vérifiez que le **Payment Entry** est soumis et lié correctement à la facture dans **Accounts > Payment Entry**.

**Variante** : Ajoutez des données pour `GlobalTech Inc` et vérifiez les statuts pour ce fournisseur.

---

### Résumé des résultats finaux
- **Supplier Quotations** : 820 USD (5 unités), 750 USD (5 unités).
- **Purchase Orders** : 2460 USD (3 unités, reçu), 1500 USD (2 unités, non reçu).
- **Purchase Invoices** : 2460 USD (unpaid), 1500 USD (paid).

### Conseils pour créer d’autres données
1. **Nouveau produit** : Ajoutez un produit comme `HP-PRINTER` (étape 1 variante).
2. **Nouveau fournisseur** : Ajoutez un fournisseur comme `GlobalTech Inc` (étape 2 variante).
3. **Nouveau flux** : Répétez les étapes 3 à 10 avec les nouveaux produit et fournisseur.
4. **Ajustements** :
   - Modifiez les quantités, prix, ou statuts (par exemple, recevez les deux commandes ou payez toutes les factures).
   - Utilisez une autre devise (par exemple, EUR au lieu de USD) en ajustant les paramètres du fournisseur et des documents.

---

Ce guide couvre toutes les étapes, inclut les solutions aux problèmes rencontrés, et fournit des variantes pour générer d’autres données. Vous êtes maintenant prêt à tester votre application ou à créer de nouvelles données similaires. Si vous avez d’autres questions ou besoins, n’hésitez pas à me les partager !