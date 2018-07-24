import { Component, forwardRef, Inject } from '@angular/core';
import { Platform } from 'ionic-angular';
import { StatusBar } from '@ionic-native/status-bar';
import { SplashScreen } from '@ionic-native/splash-screen';
import { NgRedux } from '@angular-redux/store';
import { combineEpics, createEpicMiddleware, Epic } from 'redux-observable';
import { MatchsEpics } from '../epics/matchs.epics';
import { MesMatchsEpics } from '../epics/mesMatchs.epics';
import { SessionEpics } from '../epics/session.epics';
// import { MesMatchsEpics } from '../epics/mesMatchs.epics';
import { MainPage } from '../pages/mainPage/mainPage';
import { GrowlService } from '../services/growl.service';
import { middleware } from '../store';

// import { TabsPage } from '../pages/tabs/tabs';
import { INITIAL_LOGIN_STATE } from '../store/login/login.initial-state';
import { INITIAL_MATCHS_STATE } from '../store/matchs/matchs.initial-state';
import { INITIAL_STATE_MES_MATCHS } from '../store/mesMatchs/mesMatchs.initial-state';
import { SessionActions } from '../store/session/session.actions';
import { INITIAL_STATE } from '../store/session/session.initial-state';
import { enhancers, IAppState, rootReducer } from '../store/store';
import { IPayloadAction } from '../store/types';
declare let $: any;
(window as any)['$'] = (window as any)['jQuery'] = $;
import * as _ from 'lodash';
import 'datejs';


@Component({
  templateUrl: 'app.html'
})
export class MyApp {
  rootPage:any = MainPage;

  constructor(
    platform: Platform,
    statusBar: StatusBar,
    splashScreen: SplashScreen,
    private ngRedux: NgRedux<IAppState>,
    @Inject(forwardRef(() => MesMatchsEpics)) public mesMatchsEpics: MesMatchsEpics,
    public epics: SessionEpics,
    public sessionAct: SessionActions,
    public growl: GrowlService,
    public matchEpics: MatchsEpics

  ) {
    platform.ready().then(() => {
      // Okay, so the platform is ready and our plugins are available.
      // Here you can do any higher level native things you might need.
      statusBar.styleDefault();
      splashScreen.hide();
    });

    middleware.push(createEpicMiddleware(combineEpics(
      _.bind(this.epics.login, this.epics) as Epic<IPayloadAction, IAppState>,
      _.bind(this.epics.logout, this.epics) as Epic<IPayloadAction, IAppState>,
      _.bind(this.epics.sessionError, this.epics) as Epic<IPayloadAction, IAppState>,
      _.bind(this.epics.getSalt, this.epics) as Epic<IPayloadAction, IAppState>,
      _.bind(this.epics.getSaltSessId, this.epics) as Epic<IPayloadAction, IAppState>,
      _.bind(this.matchEpics.getAll, this.matchEpics) as Epic<IPayloadAction, IAppState>,
      _.bind(this.mesMatchsEpics.getAll, this.mesMatchsEpics) as Epic<IPayloadAction, IAppState>,
      _.bind(this.mesMatchsEpics.savePointage, this.mesMatchsEpics) as Epic<IPayloadAction, IAppState>,
      _.bind(this.mesMatchsEpics.refreshAnDn, this.mesMatchsEpics) as Epic<IPayloadAction, IAppState>,
      _.bind(this.mesMatchsEpics.getFM, this.mesMatchsEpics) as Epic<IPayloadAction, IAppState>,
    )));
    this.ngRedux.configureStore(rootReducer, {
      session: INITIAL_STATE,
      login: INITIAL_LOGIN_STATE,
      matchs: INITIAL_MATCHS_STATE,
      mesMatchs: INITIAL_STATE_MES_MATCHS
    }, middleware, enhancers);

    this.sessionAct.getSaltSessionId();

  }
}
