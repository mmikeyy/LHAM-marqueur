import { Component, OnInit, OnDestroy } from '@angular/core';
import { NavController, ToastController } from 'ionic-angular';
import { ModalController } from 'ionic-angular';
import * as moment from 'moment';
import { Subscription } from 'rxjs';
import { NgRedux, select } from '@angular-redux/store';

import * as R from 'ramda';
import { MatchsService } from '../../services/matchs.service';
import { Match } from '../../store/matchs';
import { MesMatchsActions } from '../../store/mesMatchs/mesMatchs.actions';
import { IAppState } from '../../store/store';
import { InfoMatchComponent } from '../infoMatch/infoMatch.component';
import { Login } from '../login/login';
import { MenuMatchComponent } from '../menuMatch/menuMatch.component';
import { ReglerPointage } from '../reglerPointage/reglerPointage';

@Component(
  {
    selector: '',
    templateUrl: './mesMatchs.html'
  }
)
export class MesMatchs implements OnInit, OnDestroy {

  public subs: Subscription[] = [];

  public loggedIn = false;
  public liste: Match[] = [];
  public moment = moment;
  public refresher;

  @select(['session', 'user', 'id']) $id;
  @select(['mesMatchs', 'liste']) $liste;

  constructor(
    public store: NgRedux<IAppState>,
    public navCtrl: NavController,
    public mesMatchsAct: MesMatchsActions,
    public matchsServ: MatchsService,
    public modalCtrl: ModalController,
    public toastCtrl: ToastController
  ) {

  }

  ngOnInit() {
    console.log('ng init mesmatchs');
    this.subs.push(
      this.$id.subscribe(id => {
        console.log('id = ' , id);
        this.loggedIn = !!id;
      }),
      this.$liste.subscribe(liste => {
        console.log('update liste', liste);
        if (this.refresher) {
          this.refresher.complete();
          this.refresher = null;
        }
        this.liste = R.sortBy(match => match.date + match.debut, liste.valueSeq().toList().toJS());
      })
    );
    if (this.liste.length === 0) {
      this.mesMatchsAct.getAll();
    } else {
      console.log('liste len', this.liste.length + 77);
    }
  }

  ngOnDestroy() {
    this.subs.forEach(s => s.unsubscribe());
  }

  login() {
    this.navCtrl.push(Login);
  }

  entrerResultats(idMatch) {

    let page = this.modalCtrl.create(ReglerPointage, {id: idMatch});
    page.present();
  }

  doRefresh(refresher) {
    this.refresher = refresher;
    this.mesMatchsAct.getAll();
  }

  infoVerrouille() {
    let toast = this.toastCtrl.create({
      message: "Un match verrouillé ne peut être modifié. Seul l'administrateur peut le déverouiller.",
      position: 'middle',
      duration: 2000
    });
    toast.present();
  }

  showInfo(id) {
    this.matchsServ.selectMatch(id);
    this.navCtrl.push(InfoMatchComponent);
  }

  menuMatch(id: string) {
    this.mesMatchsAct.merge({idMatch: id});
    console.log('set match = ', id);
    this.navCtrl.push(MenuMatchComponent);
  }
}
