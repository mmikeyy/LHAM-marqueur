import { NgReduxRouterModule, NgReduxRouter } from '@angular-redux/router';
import { CommonModule } from '@angular/common';
import { HttpClientJsonpModule, HttpClientModule, JsonpClientBackend } from '@angular/common/http';
import { NgModule, ErrorHandler, CUSTOM_ELEMENTS_SCHEMA, NO_ERRORS_SCHEMA } from '@angular/core';
import { FormBuilder, FormsModule, ReactiveFormsModule } from '@angular/forms';
import { BrowserModule } from '@angular/platform-browser';
import { TranslateModule } from '@ngx-translate/core';
import { IonicApp, IonicModule, IonicErrorHandler } from 'ionic-angular';
import { ButComponent } from '../Components/but/but.component';
import { ChangementGardienComponent } from '../Components/changementGardien/changementGardien.component';
import { EditeChronoComponent } from '../Components/editChrono/editeChrono.component';
import { FinPeriodeComponent } from '../Components/finPeriode/finPeriode.component';
import { ChoisirJoueursFusilladeComponent } from '../pages/feuilleMatch/fusillade/choisirJoueurs/choisirJoueursFusillade.component';
import { FusilladeComponent } from '../pages/feuilleMatch/fusillade/fusillade.component';
import { PunitionComponent } from '../Components/punition/punition.component';
import { TitreEvComponent } from '../Components/titreEv/titreEv.component';

import { MatchsEpics } from '../epics/matchs.epics';
import { MesMatchsEpics } from '../epics/mesMatchs.epics';
import { SessionEpics } from '../epics/session.epics';
import { AfficherJoueursComponent } from '../pages/afficherJoueurs/afficherJoueurs.component';
import { ChoixEquipeComponent } from '../pages/choixEquipe/choixEquipe.component';
import { ChoixJoueursComponent } from '../pages/choixJoueurs/choixJoueurs.component';
import { ChoixSubstitutComponent } from '../pages/choixSubstitut/choixSubstitut.component';
import { ConfirmerSubstitutComponent } from '../pages/ConfirmerSubstitut/confirmerSubstitut.component';
import { ChoisirPeriodeComponent } from '../pages/feuilleMatch/choixPeriode/choisirPeriode.component';
import { EditeButComponent } from '../pages/feuilleMatch/editeBut/editeBut.component';
import { EditeChangementGardienComponent } from '../pages/feuilleMatch/editeChangementGardien/editeChangementGardien.component';
import { EditeFinPeriodeComponent } from '../pages/feuilleMatch/editeFinPeriode/editeFinPeriode.component';
import { EditeFusilladeComponent } from '../pages/feuilleMatch/editeFusillade/editeFusillade.component';
import { EditePunitionComponent } from '../pages/feuilleMatch/editePunition/editePunition.component';
import { InfoMatchComponent } from '../pages/infoMatch/infoMatch.component';
import { Login } from '../pages/login/login';
import { MatchsFuturs } from '../pages/matchsFuturs/matchsFuturs';
import { MenuMatchComponent } from '../pages/menuMatch/menuMatch.component';
import { MesMatchs } from '../pages/mesMatchs/mesMatchs';
import { NouveauSubstitutComponent } from '../pages/nouveauSubstitut/nouveauSubstitut.component';
import { ReglerPointage } from '../pages/reglerPointage/reglerPointage';
import { UnePeriodeComponent } from '../pages/unePeriode/unePeriode.component';
import { FilterPipe } from '../pipes/filter.pipe';
import { FilterImpurePipe } from '../pipes/filter_impure.pipe';
import { CrudService } from '../services/crud.service';
import { FmService } from '../services/fmService';
// import { ReglerPointage } from '../pages/reglerPointage/reglerPointage';
import { GrowlService } from '../services/growl.service';
import { InfoService } from '../services/info.service';
import { JoueursService } from '../services/joueursService';
import { LoginService } from '../services/login.service';
import { MatchsService } from '../services/matchs.service';
import { XhrService } from '../services/xhr.service';
import { LoginActions } from '../store/login/login.actions';
import { MatchsActions } from '../store/matchs/matchs.actions';
import { MesMatchsActions } from '../store/mesMatchs/mesMatchs.actions';
import { SessionActions } from '../store/session/session.actions';
import { MyApp } from './app.component';

import { AboutPage } from '../pages/about/about';
import { ContactPage } from '../pages/contact/contact';
import { HomePage } from '../pages/home/home';
import { TabsPage } from '../pages/tabs/tabs';

import { StatusBar } from '@ionic-native/status-bar';
import { SplashScreen } from '@ionic-native/splash-screen';
import { DevToolsExtension, NgReduxModule } from '@angular-redux/store';
import { MainPage } from '../pages/mainPage/mainPage';
import { GrowlModule } from 'primeng/growl';
import { TranslateService } from '@ngx-translate/core';
import { CookieModule } from 'ngx-cookie';
import * as moment from 'moment';
import { ForfaitComponent } from '../pages/forfait/forfait.component';
import { CollecterStatsComponent } from '../pages/collecterStats/collecterStats.component';

import { Pro } from '@ionic/pro';
import { Injectable, Injector } from '@angular/core';

Pro.init('3F2F73C3', {
  AppVersion: '0.0.1'
});
@Injectable()
export class MyErrorHandler implements ErrorHandler {
  ionicErrorHandler: IonicErrorHandler;

  constructor(injector: Injector) {
    try {
      this.ionicErrorHandler = injector.get(IonicErrorHandler);
    } catch(e) {
      // Unable to get the IonicErrorHandler provider, ensure
      // IonicErrorHandler has been added to the providers list below
    }
  }

  handleError(err: any): void {
    Pro.monitoring.handleNewError(err);
    // Remove this if you want to disable Ionic's auto exception handling
    // in development mode.
    this.ionicErrorHandler && this.ionicErrorHandler.handleError(err);
  }
}


@NgModule({
  declarations: [
    FilterPipe,
    FilterImpurePipe,
    TitreEvComponent,
    FinPeriodeComponent,
    ButComponent,
    PunitionComponent,
    ChangementGardienComponent,
    FusilladeComponent,
    MyApp,
    AboutPage,
    ContactPage,
    HomePage,
    TabsPage,
    Login,
    MainPage,
    MatchsFuturs,
    MesMatchs,
    ReglerPointage,
    InfoMatchComponent,
    MenuMatchComponent,
    ChoixEquipeComponent,
    ChoixJoueursComponent,
    ChoixSubstitutComponent,
    ConfirmerSubstitutComponent,
    NouveauSubstitutComponent,
    AfficherJoueursComponent,
    ChoisirPeriodeComponent,
    UnePeriodeComponent,
    EditeFinPeriodeComponent,
    EditeButComponent,
    EditeChronoComponent,
    EditePunitionComponent,
    EditeChangementGardienComponent,
    EditeFusilladeComponent,
    ChoisirJoueursFusilladeComponent,
    ForfaitComponent,
    CollecterStatsComponent
  ],
  imports: [
    CommonModule,
    FormsModule,
    ReactiveFormsModule,
    BrowserModule,
    IonicModule.forRoot(MyApp),
    NgReduxModule,
    NgReduxRouterModule,
    TranslateModule.forRoot({
      isolate: true
    }),
    HttpClientModule,
    HttpClientJsonpModule,
    GrowlModule,
    CookieModule.forRoot()

  ],
  bootstrap: [IonicApp],
  entryComponents: [
    MyApp,
    AboutPage,
    ContactPage,
    HomePage,
    TabsPage,
    Login,
    MainPage,
    MatchsFuturs,
    MesMatchs,
    ReglerPointage,
    InfoMatchComponent,
    MenuMatchComponent,
    ChoixEquipeComponent,
    ChoixJoueursComponent,
    ChoixSubstitutComponent,
    ConfirmerSubstitutComponent,
    NouveauSubstitutComponent,
    AfficherJoueursComponent,
    ChoisirPeriodeComponent,
    UnePeriodeComponent,
    EditeFinPeriodeComponent,
    EditeButComponent,
    EditePunitionComponent,
    EditeChangementGardienComponent,
    EditeFusilladeComponent,
    ChangementGardienComponent,
    EditeFusilladeComponent,
    FusilladeComponent,
    ChoisirJoueursFusilladeComponent,
    ForfaitComponent,
    CollecterStatsComponent
  ],
  providers: [
    DevToolsExtension,
    MesMatchsEpics,
    SessionEpics, MatchsEpics,
    FormBuilder,
    GrowlService,
    InfoService,
    JsonpClientBackend,
    LoginActions,
    LoginService,
    NgReduxRouter,
    SessionActions,
    // SessionEpics,
    MesMatchsActions,
    // MatchsEpics,
    MatchsActions,
    // MesMatchsEpics,
    SplashScreen,
    StatusBar,
    TranslateService,
    XhrService,
    // {provide: ErrorHandler, useClass: IonicErrorHandler},
    IonicErrorHandler,
    [{ provide: ErrorHandler, useClass: MyErrorHandler }],
    MatchsService,
    JoueursService,
    FmService,
    CrudService
  ],
  schemas: [CUSTOM_ELEMENTS_SCHEMA, NO_ERRORS_SCHEMA]
})
export class AppModule {
  constructor() {
    moment.locale('fr');
  }
}
