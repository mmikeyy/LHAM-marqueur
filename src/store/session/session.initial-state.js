"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var typed_immutable_record_1 = require("typed-immutable-record");
var immutable_1 = require("immutable");
exports.UserFactory = typed_immutable_record_1.makeTypedFactory({
    id_visiteur: '',
    nom_visiteur: '',
    is_editeur: false,
    publiciste: false,
    perm_structure: false,
    perm_pratiques: false,
    perm_dispo_ress: false,
    perm_admin: false,
    perm_admin_mdp: false,
    perm_admin_mdp_admin: false,
    perm_admin_perm: false,
    perm_inscription: false,
    perm_admin_inscription: false,
    perm_convert: false,
    perm_communications: false,
    perm_insertion_contenu: false,
    perm_edit_classes: false,
    perm_defilement: false,
    superhero: false,
    type_usager: '',
    perm_admin_pub: false,
    perm_comm_seulem: false,
    pub_data: immutable_1.Map(),
    eq_gerant: immutable_1.List(),
    eq_entr: immutable_1.List(),
    eq_adj: immutable_1.List()
});
exports.INITIAL_USER_STATE = exports.UserFactory();
exports.SessionFactory = function (val) {
    var session = typed_immutable_record_1.makeTypedFactory({
        token: null,
        user: exports.INITIAL_USER_STATE,
        hasError: false,
        isLoading: false,
        sel: '',
        errMsg: '',
        sessId: null
    })(val);
    if (!val) {
        return session;
    }
    // transformer le champ user en immutable map; sinon serait un simple objet
    return session.merge({
        user: exports.UserFactory(session.get('user'))
    });
};
exports.INITIAL_STATE = exports.SessionFactory();
