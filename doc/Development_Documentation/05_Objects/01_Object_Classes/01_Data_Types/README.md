# Object Data Types


The entire list of data types is indicated below:

### [Text Datatypes](./01_Text_Types.md)

| Name                     | Description                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
|--------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| input                    | text input field                                                                                                                                                                                                                                                                                                                                                                                                                                                                |
| textarea                 | textarea                                                                                                                                                                                                                                                                                                                                                                                                                                                                        |
| wysiwyg                  | text area with formatting options through a WYSIWYG editor                                                                                                                                                                                                                                                                                                                                                                                                                      |
| password                 | password field                                                                                                                                                                                                                                                                                                                                                                                                                                                                  |


### [Number Datatypes](./03_Number_Types.md)

| Name                     | Description                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
|--------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| numeric                  | spinner field for number input                                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| slider                   | number input with slider widget (min - max slider)                                                                                                                                                                                                                                                                                                                                                                                                                              |
| quantity value           | number input with an additional unit. available units can be configured centrally.  |


### [Date Datatypes](./05_Date_Types.md)

| Name                     | Description                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
|--------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| date                     | calendar date selector                                                                                                                                                                                                                                                                                                                                                                                                                                                          |
| date & time              | calendar date selector + combo box for time                                                                                                                                                                                                                                                                                                                                                                                                                                     |
| time                     | combo box for time                                                                                                                                                                                                                                                                                                                                                                                                                                     |


### [Select Datatypes](./07_Select_Types.md)

| Name                     | Description                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
|--------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| select                   | combo box                                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| user                     | combo box to select from all existing Pimcore users (available since build 716) </br></br>In the user settings the object dependencies of each user are shown in the second tab panel.</br>All objects which reference the selected user are listed in a grid view.</br></br>If one needs to find out which objects hold a reference to a specific user, the `Pimcore\\Tool\\Admin::getObjectsReferencingUser($userId)` method can be used to find all referencing objects. |
| country                  | combo box with predefined country list                                                                                                                                                                                                                                                                                                                                                                                                                   |
| language                 | combo box with predefined language list
| multiselect              | combo box with multiple select                                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| countries                | combo box with multiple select and predefined country list                                                                                                                                                                                                                                                                                                                                                                                                                    |
| languages                | combo box with multiple select and combo box with multiple select and predefined language                                                                                                                                                                                                                                                                                                                                                               |


### [Relational Datatypes](./09_Relation_Types.md)

| Name                     | Description                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
|--------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Many-To-One Relation     | reference to a Pimcore document, object or asset                                                                                                                                                                                                                                                                                                                                                                                                                                |
| Many-To-Many Relation    | collection of references to Pimcore documents, objects, assets                                                                                                                                                                                                                                                                                                                                                                                                                  |
| Advanced Many-To-Many Relation | collection of references to Pimcore documents, objects, assets with additional metadata on the relation                                                                                                                                                                                                                                                                                                                                                                                                                 |
| Many-To-Many Object Relation | collection of Pimcore object references                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| Advanced Many-To-One Object Relation | collection of Pimcore object references with additional metadata on the relation                                                                                                                                                                                                                                                                                                                                                                                                                                        |
| [Reverse Many-To-Many Object Relation](37_Reverse_Many_To_Many_Object_Relation_Type.md) | collection of Pimcore object references which are owned by a different object                                                                                                                                                                                                                                                                                                                                |


### Structured Datatypes

| Name                     | Description                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
|--------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| [block](./11_Blocks.md)  | repeatable block of attributes within an object                                                                                                                                                                                                                                                                                                                                                                                                                                                                        |
| [classificationstore](./13_Classification_Store.md)      | advanced store for classification systems like ETIM, ecl@ss, etc. |
| [table](./15_Table.md)   | table input                                                                                                                                                                                                                                                                                                                                                                                                                                                                     |
| [structuredtable](./17_Structured_Table.md)          | table with predefined rows and columns  |
| [fieldcollections](./19_Fieldcollections.md)         | A collection of fields that can be added to the object                                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| [objectbricks](./21_Object_Bricks.md)             | Bricks of attributes, that can be added to objects | 
| [localizedfields](./23_Localized_Fields.md)          | Set of attributes that can be translated |

### [Geographic Datatypes](./27_Geographic_Types.md)

| Name                     | Description                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
|--------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| geopoint                 | maps widget to find longitude/latitude                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| geobounds                | maps widget to define geographical bounds                                                                                                                                                                                                                                                                                                                                                                                                                                |
| geopolygon               | maps widget to define a geographical area                                                                                                                                                                                                                                                                                                                                                                                                                                |
| geopolyline              | maps widget to define a geographical path                                                                                                                                                                                                                                                                                                                                                                                                                                |


### Other Datatypes

| Name                     | Description                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
|--------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| [image](./29_Image_Types.md)                    | drop area & preview for a Pimcore image asset                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| [externalimage](./29_Image_Types.md#external-image-extjs6-only)            | relation to an image that is not stored in Pimcore |
| [imageadvanced](./29_Image_Types.md#image-advanced-supporting-hotspotsmarkerscropping)            | drop area & preview for a Pimcore image asset with additional features for markers, hotspots, cropping |
| [video](./31_Video_Type.md)                    | drop area & preview for a Pimcore video asset                                                                                                                                                                                                                                                                                                                                                                                                                                                                          |
| [checkbox](./35_Others.md#checkbox)                  | checkbox                                                                                                                                                                                                                                                                                                                                                                                                                                                                        |
| [link](./35_Others.md#link)                     | link selector with link target                                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| [calculatedvalue](./33_Calculated_Value_Type.md)          | datatype for calculated values - calculation can be defined with a PHP class  |


### CRM Datatypes

| Name                     | Description                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
|--------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| firstname                | typed input field for firstname |
| lastname                 | typed input field for lastname |
| email                    | typed input field for email including validation |
| gender                   | typed and prefilled select for gender |
| newsletteractive         | typed checkbox if newsletter is active |
| newsletterconfirmed      | typed checkbox if newsletter is confirmed |
| persona                  | typed selectbox for personas defined within Pimcore |
| personas                 | typed selectbox with multiselect for personas defined within Pimcore |
| [consent](./39_Consent.md)| store consent of user for something, e.g. consent for direct marketing mailing |



### General Aspects

All data types are wrapped in an object derived from `Pimcore\Model\DataObject\Class\Data`. 
These data type objects provide getters and setters and they define the Description in the frontend. 
Data type objects are displayed in the first column of the table above. 
The second column indicates the underlying data type class and the third column outlines the Description used in Pimcore 
to fill in, edit and display data objects.


Besides the `name`, which is the name of the object's property and the `title`, which is shown in the GUI, an 
object field has the general configuration options listed below. The title can be translated for different system 
languages. Please see the article about Translations to find out how to add object field translations.

* `mandatory`: Makes the field mandatory and does not allow saving the object when it is empty
* `not editable`: Does not allow a change of this field's value in Pimcore backend (data change can only be done 
  programmatically)
* `invisible`: The field is not visible in Pimcore
* `visible in grid view`: Determines if the field's data column is shown in the object grid view, or hidden 
  (meaning it has to be activated manually)
* `visible in search result`: Determines if the field's data column is shown in the search results grid, or hidden 
  (meaning it has to be activated manually)
* `indexed`: puts an index on this column in the database
Moreover, each data field can have a `tooltip`, which is shown when the mouse hovers over the input field.
* `unique`: currently the `input` and `numeric` data type allows to add a unique constraint.If checked, the values will also be indexed. Note that only works on top level attributes and not on nested stuff inside localized fields etc.

![Data Field Settings](../../../img/classes-datatypes1.jpg)
![Data Field Settings](../../../img/classes-datatypes2.jpg)


The `layout settings` allow to apply custom CSS to any object field.


![Data Field Settings](../../../img/classes-datatypes3.jpg)


> **WARNING**  
> Please note that renaming a field means the loss of data from the field in all objects using this class.

See sub-pages of this page for detail documentation of different data types. 
