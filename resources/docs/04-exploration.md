# Outils d'Exploration

Le MMS propose une interface dédiée pour naviguer dans vos archives de manière intuitive : la **Vue Hiérarchique**.

## Vue Hiérarchique

Accessible via le menu [Explorateur](route:filament.mms-admin.pages.hierarchy-explorer), cette page vous permet de parcourir l'arborescence complète de vos données sans passer par les tables de liste classiques.

### Modes d'exploration

L'outil propose deux modes de vue, sélectionnables via des onglets ou des boutons (selon la version) :

1.  **Mode Collections** (par défaut) :
    *   Ce mode se concentre sur les Collections et leur contenu.
    *   La colonne de gauche liste les Collections.
    *   En cliquant sur une Collection, la colonne centrale affiche les Items qu'elle contient.

2.  **Mode Fonds** :
    *   Ce mode offre une vue complète depuis la racine.
    *   La colonne de gauche affiche l'arborescence Fonds > Corpus.
    *   En sélectionnant un élément, vous descendez dans la hiérarchie jusqu'aux Items.

### Navigation à colonnes

L'interface est divisée en plusieurs colonnes dynamiques :

*   **Colonne de Gauche (Navigation)** : Permet de sélectionner le conteneur (Fonds, Corpus ou Collection). Vous pouvez y effectuer des recherches pour filtrer la liste.
*   **Colonne Centrale (Items)** : Affiche la liste des fichiers contenus dans l'élément sélectionné à gauche.
    *   Les items sont regroupés : les **Meta Items** (items secondaires) peuvent être distingués des items standards.
    *   Vous pouvez cliquer sur un item pour voir ses détails.
*   **Colonne de Droite (Détails)** (si active) : Affiche les informations détaillées de l'élément ou de l'item sélectionné (métadonnées, relations).

### Recherche

Une barre de recherche globale en haut de la vue permet de filtrer les éléments affichés dans les colonnes. La recherche s'applique sur les codes et les titres.

### Actions contextuelles

Selon l'élément sélectionné, des boutons d'action vous permettent de :
*   Voir la fiche complète de l'élément.
*   Ajouter un sous-élément (ex: ajouter un Item dans la Collection sélectionnée).
