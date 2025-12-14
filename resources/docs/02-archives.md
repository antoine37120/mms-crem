# Gestion des Archives

Cette section décrit comment gérer la structure hiérarchique de vos archives : Fonds, Corpus et Collections.

## Fonds

Les **Fonds** constituent la racine de votre archivage. Ils représentent généralement un producteur d'archives ou une grande thématique.

### Créer un Fonds
1.  Allez dans le menu [Gestion des Archives > Fonds](route:filament.mms-admin.resources.fonds.index).
2.  Cliquez sur le bouton **Créer**.
3.  Renseignez le **Code** (identifiant unique, ex: `CNRSMH_Arnaud`) et le **Titre**.
4.  Validez.

### Gérer un Fonds
Depuis la liste des fonds, vous pouvez éditer ou supprimer un fonds existant. La page de détail d'un fonds vous permet également de voir rapidement les corpus qui lui sont rattachés.

## Corpus

Les **Corpus** sont des subdivisions d'un fonds. Ils permettent d'organiser les archives par thèmes, périodes ou projets.

### Créer un Corpus
1.  Allez dans le menu [Gestion des Archives > Corpus](route:filament.mms-admin.resources.corpuses.index).
2.  Cliquez sur le bouton **Créer**.
3.  Sélectionnez le **Fonds parent**.
4.  Le système peut suggérer un code basé sur le parent. Renseignez ou ajustez le **Code** et le **Titre**.

## Collections

Les **Collections** regroupent les items (enregistrements). C'est le niveau où sont généralement classés les médias.

### Créer une Collection
1.  Allez dans le menu [Gestion des Archives > Collections](route:filament.mms-admin.resources.collections.index).
2.  Cliquez sur le bouton **Créer**.
3.  Sélectionnez le **Corpus parent**.
4.  Renseignez le **Code** et le **Titre**.

![Screenshot: Formulaire de création d'une collection]

> **Note :** La suppression d'un élément (Fonds, Corpus ou Collection) peut entraîner la suppression ou le détachement de ses enfants. Soyez vigilants.
