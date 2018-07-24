import { Component, OnInit, OnDestroy, ViewChild } from '@angular/core';
import { AlertController, NavController, NavParams } from 'ionic-angular';
import { Subscription } from 'rxjs';
import { NgRedux, select } from '@angular-redux/store';
import { FmService } from '../../services/fmService';
import { MesMatchsActions } from '../../store/mesMatchs/mesMatchs.actions';
import { evFactory } from '../../store/mesMatchs/mesMatchs.initial-state';
import { FeuilleMatch } from '../../store/mesMatchs/mesMatchs.types';
import { IAppState } from '../../store/store';
import * as _ from 'lodash';
import * as R from 'ramda';
import { EditeButComponent } from '../feuilleMatch/editeBut/editeBut.component';
import { EditeChangementGardienComponent } from '../feuilleMatch/editeChangementGardien/editeChangementGardien.component';
import { EditePunitionComponent } from '../feuilleMatch/editePunition/editePunition.component';

@Component(
  {
    selector: '',
    templateUrl: './unePeriode.component.html'
  }
)
export class UnePeriodeComponent implements OnInit, OnDestroy {

  public subs: Subscription[] = [];
  public idMatch;
  public editable = false;

  @ViewChild('anSlidingItem') anSlidingItem;

  @select(['mesMatchs', 'feuilleMatch']) $fm;
  @select(['mesMatchs', 'idMatchCharge']) $idMatch;

  constructor(
    public store: NgRedux<IAppState>,
    public fmServ: FmService,
    public navCtrl: NavController,
    public alertCtrl: AlertController,
    public params: NavParams
  ) {

  }

  ngOnInit() {
    this.subs.push();
    this.idMatch = this.params.get('idMatch');
    this.editable = this.params.get('editable');
  }

  ngOnDestroy() {
    this.subs.forEach(s => s.unsubscribe());
  }

  ajouterBut() {
    let ev: FeuilleMatch = {
      id_match: this.idMatch,
      periode: this.fmServ.periodeChoisie,
      type_enreg: 'but',
      chrono: '00:00',
      avantage_numerique: 0
    };
    this.navCtrl.push(EditeButComponent, {ev})
  }

  ajouterPunition() {
    let ev: FeuilleMatch = {
      id_match: this.idMatch,
      periode: this.fmServ.periodeChoisie,
      type_enreg: 'punition',
      chrono: '00:00',
      codes_punition: ''
    };
    this.navCtrl.push(EditePunitionComponent, {ev})
  }
  ajouterChangementGardien() {
    let ev: FeuilleMatch = {
      id_match: this.idMatch,
      periode: this.fmServ.periodeChoisie,
      type_enreg: 'changement_gardien',
      chrono: '00:00',
    };
    this.navCtrl.push(EditeChangementGardienComponent, {ev})
  }

  showInfoAN() {
    this.anSlidingItem.close();
    let alert = this.alertCtrl.create({
      title: 'Avantages numériques',
      message: `Les avantages et désavantages numériques seront mis à jour lors de la collection
      des statistiques du match. Leur mise à jour ici est optionnelle.`,
      buttons: [
        {text: 'OK'}
      ]
    });
    alert.present();
  }

  refreshAnDn() {
    this.anSlidingItem.close();
    this.store.dispatch({
      type: MesMatchsActions.MESMATCHS_REFRESH_AN_DN,
      payload: {idMatch: this.idMatch}
    })
  }
}
