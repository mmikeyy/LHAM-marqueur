

import persistState from 'redux-localstorage'
let a = require('redux-logger/dist/redux-logger.js');
// import { createLogger } from 'redux-logger'
import { deimmutify, reimmutify } from './store';
// import { applyMiddleware, createStore } from 'redux';

//

// let a = require('redux-logger/dist/redux-logger.js');


declare var ENV: string;


export let middleware = [];
export let enhancers = [
  persistState(
    '',
    {
      key: 'store',
      serialize: store => JSON.stringify(deimmutify(store)),
      deserialize: state => reimmutify(JSON.parse(state)),
    })
];

if (ENV !== 'production') {
  middleware.push(
    a.createLogger({
    level: 'info',
    collapsed: true,
    stateTransformer: deimmutify,
  }));

  const environment: any = window || this;
  if (environment.devToolsExtension) {
    enhancers.push(environment.devToolsExtension());
    console.log('...........dev tools');
  }
  console.log('---------------- fin');
}
