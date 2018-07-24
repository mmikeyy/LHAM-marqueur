"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var session_actions_1 = require("../../actions/session.actions");
var session_initial_state_1 = require("./session.initial-state");
var login_actions_1 = require("../../actions/login.actions");
var immutable_1 = require("immutable");
function sessionReducer(state, action) {
    if (state === void 0) { state = session_initial_state_1.INITIAL_STATE; }
    switch (action.type) {
        case login_actions_1.LoginActions.LOGIN_SUBMIT:
            return state.merge({
                token: null,
                user: session_initial_state_1.INITIAL_USER_STATE,
                hasError: false,
                isLoading: true
            });
        case session_actions_1.SessionActions.LOGIN_USER_SUCCESS:
            return state.merge({
                token: action.payload.token,
                user: session_initial_state_1.UserFactory(immutable_1.fromJS(action.payload)),
                hasError: false,
                isLoading: false
            });
        case session_actions_1.SessionActions.LOGIN_USER_ERROR:
            return state.merge({
                token: null,
                user: session_initial_state_1.INITIAL_USER_STATE,
                hasError: true,
                isLoading: false
            });
        case session_actions_1.SessionActions.SESSION_SET_SALT:
            return state.set('sel', action.payload.sel);
        case session_actions_1.SessionActions.SESSION_ERROR:
            var pl = action.payload;
            return state.merge({
                errMsg: pl.msg + (pl.ref ? " (" + pl.ref + ")" : ''),
                hasError: true,
                isLoading: false
            });
        case session_actions_1.SessionActions.LOGOUT_USER:
            return session_initial_state_1.INITIAL_STATE;
        case session_actions_1.SessionActions.SESSION_CLR_ERROR:
            return state.merge({
                hasError: false,
                errMsg: ''
            });
        case session_actions_1.SessionActions.SESSION_SET_SALT_SESSID:
            console.log('set salt sessid', action.payload);
            return state.merge({
                sel: action.payload.sel,
                sessId: action.payload.sessId
            });
        case session_actions_1.SessionActions.SESSION_INIT:
            return state.merge({
                hasError: false,
                errMsg: '',
                isLoading: false
            });
        case session_actions_1.SessionActions.SESSION_SET:
            return state.merge(action.payload);
        default:
            return state;
    }
}
exports.sessionReducer = sessionReducer;
