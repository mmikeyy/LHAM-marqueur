import { Component, OnInit, OnDestroy } from '@angular/core';
import { NavController, ToastController } from 'ionic-angular';
import { Subscription } from 'rxjs';
import { NgRedux, select } from '@angular-redux/store';
import { FmService } from '../../services/fmService';
import { JoueursService } from '../../services/joueursService';
import { MatchsService } from '../../services/matchs.service';
import { IAppState } from '../../store/store';
import * as R from 'ramda';
import { ChoixJoueursComponent } from '../choixJoueurs/choixJoueurs.component';

@Component(
  {
    templateUrl: './choixEquipe.component.html'
  }
)
export class ChoixEquipeComponent implements OnInit, OnDestroy {

  public subs: Subscription[] = [];
  public idMatch;
  public idEquipe1;
  public nomEquipe1;
  public idEquipe2;
  public nomEquipe2;
  public eq = [];

  constructor(
    public store: NgRedux<IAppState>,
    public matchsServ: MatchsService,
    public joueursServ: JoueursService,
    public navCtrl: NavController,
    public toastCtrl: ToastController,
    public fmServ: FmService
  ) {
    console.log('CCCCConstruct choix equipe');

  }

  ngOnInit() {
    this.subs.push(
    );
    this.prep();
  }

  async prep() {


    await this.joueursServ.initialize();

    this.idMatch = this.joueursServ.idMatch;
    this.idEquipe1 = this.joueursServ.idEquipe1;
    this.idEquipe2 = this.joueursServ.idEquipe2;

    this.nomEquipe1 = this.matchsServ.nomEq(this.idEquipe1);
    this.nomEquipe2 = this.matchsServ.nomEq(this.idEquipe2);
    this.eq = [
      {
        id: this.idEquipe1,
        nom: this.nomEquipe1,
        forfait: this.joueursServ.forfait1
      },
      {
        id: this.idEquipe2,
        nom: this.nomEquipe2,
        forfait: this.joueursServ.forfait2
      }
    ];
  }

  ngOnDestroy() {
    this.subs.forEach(s => s.unsubscribe());
  }

  choisir(idEquipe) {
    if (this.joueursServ.choisirEquipe(idEquipe)) {
      this.navCtrl.push(ChoixJoueursComponent);
    } else {
      let toast = this.toastCtrl.create({
        message: `Équipe non reconnue pour ce match (#${idEquipe})`,
        duration: 3000
      });
      toast.present();
    }
  }

  compte(idEquipe) {
    return R.filter(val => (val['id_equipe_match'] || val['id_equipe']) === idEquipe && val.choisi, this.joueursServ.joueurs).length
  }

  gardienDesigne(idEquipe) {
    return R.filter(val => (val['id_equipe_match'] || val['id_equipe']) === idEquipe && val.choisi && val.position === 2, this.joueursServ.joueurs).length > 0
  }

  async soumettre() {
    let succes  = await this.joueursServ.soumettreJoueurs();
    if (succes) {
      this.navCtrl.pop();
    }
  }

}
