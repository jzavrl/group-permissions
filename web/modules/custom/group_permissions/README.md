# Group access

This module provides custom logic for access checks on different entities and
bundles. It is done based on the Group entity. Owner or author of the entity is
added into a Group and if the user viewing the content is in the same Group as
the author and has the proper permissions within that Group then grant him
access.

Some entities will have this access checks, but some also won't. There are three
main parts which need to be done in code in order for an entity and bundle to be
applicable for the Group access logic.

## Entity control handler

First part is to create a custom control handler which extends the entities
original/default one. A good starting point is the
`src/Access/AssessmentTypeAccessControlHandler`. It is advised to use the
`AccessControlTrait` as it will provide the necessary shared services that are
required for the logic. In the handler you need to have the `checkAccess`
method and the minimal code is presented in the same handler.

### Enabling the control handler

Next thing is to update the `HANDLERS` constant in the `src/Access/AccessManager` service. 
A new line should be added with the key being the entity type machine name and the
value being the new handler class.

This change updates the entity type definition to use our new one. This is automatically 
done through the `group_permissions.module` implementing the `hook_entity_type_alter` hook. 

## GroupContentEnabler

Final piece is to create a GroupContentEnabler plugin, which will allow the
entity and bundle to be added into the Group as part of the Group Content
entity. This will also enable us to set specific per
role permissions for that entity/bundle.

A good example here to look at and to start of with is the
`src/Plugin/GroupContentEnabler/GroupAccessment`. This consists of two
parts. The deriver and the plugin. The deriver will be providing the plugin with
the necessary information for the bundles, while the plugin will use that and
create the necessary configuration for the Group. So instead of creating 10
plugins for each of the bundle, the deriver will provide all that for you
automatically.

Both files should be duplicated and renamed according to proper naming (entity
type). Also the contents of each file should be replaced accordingly. Any
occurrences of AssessmentType should be replaced with the new entity type name.
Also make sure to properly name the plugin ID, name, description, type and the
deriver class.

## Configure permissions in the group UI

To set up the entity for group permissions it must be enabled on the 'set
available content' page for each group type.  Once marked as 'installed' the
permissions can be configured for the different roles.

## Access Special Case

There can be special cases for Group Access to take into account. These cases
are handled through the AccessSpecialCase plugins which take priority over the
usual Group Access permissions. These can be used anywhere in the codebase and
to use them you must create a file and put it into the
`src/Plugin/AccessSpecialCase` folder. The class needs to extend the
`AccessSpecialCaseBase` class and implement the `checkAccess` method. There is
also a proper annotation which needs to be added.

### Annotation

```
/**
 * Description on what the access logic is.
 *
 * @AccessSpecialCase(
 *   id = "machine_id",
 *   label = @Translation("Human label"),
 *   type = "allowed",
 *   entity_type_id = {},
 *   excluded_entity_type_id = {},
 * )
 */
```

All of the keys need to be present, id and label is there for the obvious
reasons of a plugin, while others are a bit more complex.

- `type` defines what the access will be, can be `allowed` or `forbidden`.
Allowed will allow access and forbidden will forbid access. Based on this it is
also important to note what the `checkAccess` method will return. vIt always
needs to return boolean and a value of `TRUE` will allow access or forbid access
depending on the `type` set in the annotation.
- `entity_type_id` array defines on which entity types the plugin should act on.
In case of an empty array it acts on all.
- `excluded_entity_type_id` array defines on which entity types the plugin
should NOT act on.
