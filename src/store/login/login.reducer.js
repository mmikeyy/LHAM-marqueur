"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var login_actions_1 = require("../../actions/login.actions");
var login_initial_state_1 = require("./login.initial-state");
var session_actions_1 = require("../../actions/session.actions");
function loginReducer(state, action) {
    if (state === void 0) { state = login_initial_state_1.INITIAL_LOGIN_STATE; }
    switch (action.type) {
        case 'show':
            console.log(state, 'action', action);
            break;
        case login_actions_1.LoginActions.LOGIN_DIAL:
            return state.set('status', login_actions_1.LoginActions.LOGIN_DIAL);
        case login_actions_1.LoginActions.LOGIN_MDP_PERDU_DIAL:
            return state.set('status', login_actions_1.LoginActions.LOGIN_MDP_PERDU_DIAL);
        case login_actions_1.LoginActions.LOGIN_NOUVEAU_MDP_DIAL:
            return state.set('status', login_actions_1.LoginActions.LOGIN_NOUVEAU_MDP_DIAL);
        case login_actions_1.LoginActions.LOGIN_DISPLAY_STATUS:
            return state.merge({ status: login_actions_1.LoginActions.LOGIN_DISPLAY_STATUS, processing: false });
        case login_actions_1.LoginActions.LOGIN_LOGGING_OUT:
            return state.merge({
                status: login_actions_1.LoginActions.LOGIN_LOGGING_OUT,
                processing: true
            });
        case login_actions_1.LoginActions.LOGIN_LOGOUT_ERROR:
            return state.set('status', login_actions_1.LoginActions.LOGIN_LOGOUT_ERROR);
        case login_actions_1.LoginActions.LOGIN_SUBMIT:
            // voir epics
            return state.set('processing', true);
        case session_actions_1.SessionActions.LOGIN_USER_SUCCESS:
            return state.merge({
                processing: false,
                status: login_actions_1.LoginActions.LOGIN_DISPLAY_STATUS,
                pseudo: action.payload.pseudo
            });
        case login_actions_1.LoginActions.LOGIN_CHG_DONNEES:
            return state.merge({
                status: login_actions_1.LoginActions.LOGIN_CHG_DONNEES
            });
        case login_actions_1.LoginActions.LOGIN_PERDU_MDP:
            return state.merge({
                status: login_actions_1.LoginActions.LOGIN_PERDU_MDP
            });
        case login_actions_1.LoginActions.LOGIN_CODE_MDP_PERDU_ENVOYE:
            var val = { adresseMdpPerduEnvoye: action.payload.adresse };
            if (action.payload.code !== undefined) {
                val.codeValidation = +action.payload.code;
            }
            return state.merge(val);
        case login_actions_1.LoginActions.LOGIN_CHANGER_MDP:
            return state.merge({
                status: login_actions_1.LoginActions.LOGIN_CHANGER_MDP
            });
        case login_actions_1.LoginActions.LOGIN_CHANGER_PSEUDO:
            return state.set('pseudo', action.payload.pseudo);
        default:
    }
    return state;
}
exports.loginReducer = loginReducer;
