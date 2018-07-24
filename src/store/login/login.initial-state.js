"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var typed_immutable_record_1 = require("typed-immutable-record");
var login_actions_1 = require("../../actions/login.actions");
exports.LoginFactory = typed_immutable_record_1.makeTypedFactory({
    status: login_actions_1.LoginActions.LOGIN_DISPLAY_STATUS,
    processing: false,
    pseudo: '',
    adresseMdpPerduEnvoye: '',
    codeValidation: 0
});
exports.INITIAL_LOGIN_STATE = exports.LoginFactory();
